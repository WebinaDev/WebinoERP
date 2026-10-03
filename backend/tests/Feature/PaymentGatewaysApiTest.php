<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Entities\SystemModule;
use Modules\Integrations\Entities\ModirPayamakAccount;
use Modules\Integrations\Entities\ModirPayamakPackage;
use Modules\Integrations\Entities\PaymentIntent;
use Modules\Integrations\Entities\PaymentWallet;
use Modules\Integrations\Services\Payments\PaymentCallbackSigner;
use Modules\Sales\Entities\SalesInvoice;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class PaymentGatewaysApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        SystemModule::create(['name' => 'Integrations', 'slug' => 'integrations', 'is_active' => true]);
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);
    }

    public function test_settings_mask_secrets_and_reject_invalid_rows(): void
    {
        $production = $this->putJson('/api/v1/integrations/payments/gateways', [
            'gateways' => [
                'zarinpal' => $this->zarinpalRow(['sandbox' => false, 'merchant_id' => '']),
            ],
        ])->assertStatus(422);
        $this->assertArrayHasKey('zarinpal.merchant_id', $production->json('errors') ?? []);

        $saved = $this->putJson('/api/v1/integrations/payments/gateways', [
            'gateways' => [
                'zarinpal' => $this->zarinpalRow(['merchant_id' => 'merchant-secret-1234']),
            ],
        ])->assertOk()
            ->assertJsonPath('data.0.code', 'zarinpal')
            ->assertJsonPath('data.0.label_fa', 'زرین‌پال')
            ->assertJsonPath('data.0.fields.0.masked', '••••1234');
        $this->assertStringNotContainsString('merchant-secret-1234', $saved->getContent());

        $kept = $this->putJson('/api/v1/integrations/payments/gateways', [
            'gateways' => [
                'zarinpal' => $this->zarinpalRow(),
            ],
        ])->assertOk();
        $this->assertSame('••••1234', $kept->json('data.0.fields.0.masked'));

        $fee = $this->putJson('/api/v1/integrations/payments/gateways', [
            'gateways' => [
                'zarinpal' => $this->zarinpalRow(['fee_percent' => 150]),
            ],
        ])->assertStatus(422);
        $this->assertSame('کارمزد باید عددی بین ۰ و ۱۰۰ باشد.', $fee->json('errors')['zarinpal.fee_percent'] ?? null);

        $mode = $this->putJson('/api/v1/integrations/payments/gateways', [
            'gateways' => [
                'zarinpal' => $this->zarinpalRow(['enabled' => true, 'cash_enabled' => false]),
            ],
        ])->assertStatus(422);
        $this->assertArrayHasKey('zarinpal.mode', $mode->json('errors') ?? []);

        $installment = $this->putJson('/api/v1/integrations/payments/gateways', [
            'gateways' => [
                'zarinpal' => $this->zarinpalRow(['installment_enabled' => true]),
            ],
        ])->assertStatus(422);
        $this->assertArrayHasKey('zarinpal.installment_enabled', $installment->json('errors') ?? []);
    }

    public function test_disabled_gateway_is_absent_from_options_and_quote_applies_fee(): void
    {
        $this->enableZarinpal();
        $this->putJson('/api/v1/integrations/payments/gateways', [
            'gateways' => [
                'snappay' => [
                    'enabled' => false,
                    'sandbox' => true,
                    'cash_enabled' => false,
                    'installment_enabled' => true,
                    'fee_percent' => 10,
                    'apply_fee_on_cash' => false,
                ],
            ],
        ])->assertOk();

        $options = $this->getJson('/api/v1/integrations/payments/options')->assertOk();
        $cashCodes = collect($options->json('data.cash'))->pluck('code')->all();
        $installmentCodes = collect($options->json('data.installment'))->pluck('code')->all();
        $this->assertContains('zarinpal', $cashCodes);
        $this->assertNotContains('snappay', $cashCodes);
        $this->assertNotContains('snappay', $installmentCodes);

        $this->postJson('/api/v1/integrations/payments/quote', [
            'gateway' => 'zarinpal',
            'mode' => 'cash',
            'base_amount' => 100000,
        ])->assertOk()
            ->assertJsonPath('data.fee_amount', 0)
            ->assertJsonPath('data.total_amount', 100000);

        $this->enableSnapp(10);
        $this->postJson('/api/v1/integrations/payments/quote', [
            'gateway' => 'snappay',
            'mode' => 'installment',
            'base_amount' => 100000,
        ])->assertOk()
            ->assertJsonPath('data.fee_amount', 10000)
            ->assertJsonPath('data.total_amount', 110000)
            ->assertJsonPath('data.fee_applied', true);

        $options = $this->getJson('/api/v1/integrations/payments/options?mode=installment')->assertOk();
        $this->assertContains('snappay', collect($options->json('data.gateways'))->pluck('code')->all());
        $this->assertNotContains('zarinpal', collect($options->json('data.gateways'))->pluck('code')->all());
    }

    public function test_sandbox_zarinpal_callback_pays_invoice_and_rejects_bad_signature_or_cancel(): void
    {
        $this->enableZarinpal(2.5, true);
        $invoice = SalesInvoice::create([
            'number' => 'INV-PAY-1',
            'customer_name' => 'ACME',
            'total' => 100000,
            'subtotal' => 100000,
            'discount' => 0,
            'status' => 'issued',
            'issue_date' => now()->toDateString(),
        ]);

        $started = $this->postJson('/api/v1/integrations/payments/intents', [
            'payable_type' => 'sales_invoice',
            'payable_id' => (string) $invoice->id,
            'mode' => 'cash',
            'gateway' => 'zarinpal',
            'return_url' => 'https://erp.example.com/billing/return',
        ])->assertCreated()
            ->assertJsonPath('data.fee_amount', 2500)
            ->assertJsonPath('data.total_amount', 102500)
            ->assertJsonPath('data.status', 'redirected');

        $redirect = (string) $started->json('data.redirect_url');
        $this->assertStringContainsString('Status=OK', $redirect);

        $bad = $this->callbackQuery($redirect, ['sig' => 'deadbeef']);
        $this->getJson($bad)->assertStatus(422);
        $this->assertSame('issued', $invoice->fresh()->status);

        $cancelled = $this->callbackQuery($redirect, ['Status' => 'NOK']);
        $this->getJson($cancelled)->assertStatus(422);
        $this->assertSame('failed', PaymentIntent::query()->where('public_id', $started->json('data.payment_id'))->value('status'));
        $this->assertSame('issued', $invoice->fresh()->status);

        $again = $this->postJson('/api/v1/integrations/payments/intents', [
            'payable_type' => 'sales_invoice',
            'payable_id' => (string) $invoice->id,
            'mode' => 'cash',
            'gateway' => 'zarinpal',
        ])->assertCreated();
        $this->getJson($this->callbackQuery((string) $again->json('data.redirect_url')))
            ->assertOk()
            ->assertJsonPath('data.status', 'settled');
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame('cash:zarinpal', $invoice->fresh()->payment_method);
    }

    public function test_snapp_digipay_and_torob_verify_and_settle_over_http(): void
    {
        $invoice = SalesInvoice::create([
            'number' => 'INV-PAY-2',
            'customer_name' => 'ACME',
            'total' => 200000,
            'subtotal' => 200000,
            'discount' => 0,
            'status' => 'issued',
            'issue_date' => now()->toDateString(),
        ]);

        $this->fakeSnappStyle('https://snapp.test');
        $this->enableOauthGateway('snappay', 'https://snapp.test', 5);
        $snapp = $this->startInstallment('snappay', $invoice->id);
        $this->assertSame('https://snapp.test/pay/pay-token', $snapp->json('data.redirect_url'));
        $this->callbackProvider('snappay', (string) $snapp->json('data.payment_id'), [
            'state' => 'OK',
            'amount' => 210000,
        ])->assertOk()->assertJsonPath('data.status', 'settled');

        $second = SalesInvoice::create([
            'number' => 'INV-PAY-3',
            'customer_name' => 'ACME',
            'total' => 80000,
            'subtotal' => 80000,
            'discount' => 0,
            'status' => 'issued',
            'issue_date' => now()->toDateString(),
        ]);
        Http::fake([
            'https://digipay.test/*' => function ($request) {
                $url = $request->url();
                if (str_contains($url, '/oauth/token')) {
                    return Http::response(['access_token' => 'digi-token', 'expires_in' => 3600], 200);
                }
                if (str_contains($url, '/tickets/business')) {
                    return Http::response(['result' => ['status' => 0], 'redirectUrl' => 'https://digipay.test/pay', 'ticket' => 'ticket-1'], 200);
                }
                if (str_contains($url, '/purchases/verify')) {
                    return Http::response(['result' => ['status' => 0], 'rrn' => 'rrn-9'], 200);
                }
                if (str_contains($url, '/purchases/deliver')) {
                    return Http::response(['result' => ['status' => 0]], 200);
                }

                return Http::response(['url' => $url], 500);
            },
        ]);
        $this->enableOauthGateway('digipay', 'https://digipay.test/digipay/api', 0, true);
        $digi = $this->postJson('/api/v1/integrations/payments/intents', [
            'payable_type' => 'sales_invoice',
            'payable_id' => (string) $second->id,
            'mode' => 'cash',
            'gateway' => 'digipay',
            'mobile' => '09121111111',
        ])->assertCreated();
        $this->assertSame('https://digipay.test/pay', $digi->json('data.redirect_url'));
        $this->callbackProvider('digipay', (string) $digi->json('data.payment_id'), [
            'result' => 'SUCCESS',
            'trackingCode' => 'ticket-1',
            'amount' => 80000,
        ])->assertOk()->assertJsonPath('data.status', 'settled');
        $this->assertSame('paid', $second->fresh()->status);

        $third = SalesInvoice::create([
            'number' => 'INV-PAY-4',
            'customer_name' => 'ACME',
            'total' => 90000,
            'subtotal' => 90000,
            'discount' => 0,
            'status' => 'issued',
            'issue_date' => now()->toDateString(),
        ]);
        $this->fakeSnappStyle('https://torob.test');
        $this->enableOauthGateway('torobpay', 'https://torob.test', 0);
        $torob = $this->startInstallment('torobpay', $third->id);
        $this->callbackProvider('torobpay', (string) $torob->json('data.payment_id'), [
            'state' => 'OK',
            'amount' => 90000,
        ])->assertOk()->assertJsonPath('data.status', 'settled');
        $this->assertSame('paid', $third->fresh()->status);
    }

    public function test_sms_credit_is_applied_once(): void
    {
        $this->enableZarinpal();
        $package = ModirPayamakPackage::create([
            'name' => 'Starter',
            'amount' => 100000,
            'sms_units' => 200,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $started = $this->postJson('/api/v1/integrations/payments/intents', [
            'payable_type' => 'sms_credit',
            'payable_id' => (string) $package->id,
            'domain' => 'client.example.com',
            'mode' => 'cash',
            'gateway' => 'zarinpal',
        ])->assertCreated()
            ->assertJsonPath('data.payable_type', 'sms_order');

        $path = $this->callbackQuery((string) $started->json('data.redirect_url'));
        $this->getJson($path)->assertOk()->assertJsonPath('data.status', 'settled');
        $this->getJson($path)->assertOk()->assertJsonPath('data.status', 'settled');

        $account = ModirPayamakAccount::query()->where('domain', 'client.example.com')->first();
        $this->assertNotNull($account);
        $this->assertSame(200.0, (float) $account->balance);
    }

    public function test_tenant_wallet_session_requires_hmac_and_returns_redirect(): void
    {
        $this->enableZarinpal();
        config(['app.webinocrm_license_hmac_secret' => 'test-secret']);
        $domain = 'client.example.com';
        $product = 'webinodashboard';
        $ts = time();

        $this->postJson('/api/webinocrm/v1/payments/sessions', [
            'domain' => $domain,
            'product' => $product,
            'ts' => $ts,
            'signature' => 'deadbeef',
            'payable_type' => 'wallet_topup',
            'mode' => 'cash',
            'gateway' => 'zarinpal',
            'return_url' => 'https://dashboard.example.com/billing',
            'amount' => 50000,
        ])->assertStatus(401);

        $sig = hash_hmac('sha256', $domain.'|'.$product.'|'.$ts, 'test-secret');
        $session = $this->postJson('/api/webinocrm/v1/payments/sessions', [
            'domain' => $domain,
            'product' => $product,
            'ts' => $ts,
            'signature' => $sig,
            'payable_type' => 'wallet_topup',
            'mode' => 'cash',
            'gateway' => 'zarinpal',
            'return_url' => 'https://dashboard.example.com/billing',
            'amount' => 50000,
        ])->assertCreated()
            ->assertJsonPath('data.gateway', 'zarinpal')
            ->assertJsonPath('data.base_amount', 50000)
            ->assertJsonPath('data.total_amount', 50000);
        $this->assertNotEmpty($session->json('data.redirect_url'));

        $paymentId = (string) $session->json('data.payment_id');
        $this->getJson($this->callbackQuery((string) $session->json('data.redirect_url')))
            ->assertOk()
            ->assertJsonPath('data.status', 'settled');

        $wallet = PaymentWallet::query()->where('domain', $domain)->first();
        $this->assertNotNull($wallet);
        $this->assertSame(50000.0, (float) $wallet->balance);

        $this->postJson('/api/webinocrm/v1/payments/sessions/'.$paymentId, [
            'domain' => $domain,
            'product' => $product,
            'ts' => $ts,
            'signature' => $sig,
        ])->assertOk()->assertJsonPath('data.status', 'settled');
    }

    public function test_local_connection_check_succeeds_for_enabled_sandbox(): void
    {
        $this->enableZarinpal();
        $this->postJson('/api/v1/integrations/payments/gateways/zarinpal/test')
            ->assertOk()
            ->assertJsonPath('data.ok', true);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function zarinpalRow(array $overrides = []): array
    {
        return array_merge([
            'enabled' => true,
            'sandbox' => true,
            'cash_enabled' => true,
            'installment_enabled' => false,
            'fee_percent' => 0,
            'apply_fee_on_cash' => false,
        ], $overrides);
    }

    private function enableZarinpal(float $fee = 0, bool $feeOnCash = false): void
    {
        $this->putJson('/api/v1/integrations/payments/gateways', [
            'gateways' => [
                'zarinpal' => $this->zarinpalRow([
                    'fee_percent' => $fee,
                    'apply_fee_on_cash' => $feeOnCash,
                ]),
            ],
        ])->assertOk();
    }

    private function enableSnapp(float $fee): void
    {
        $this->putJson('/api/v1/integrations/payments/gateways', [
            'gateways' => [
                'snappay' => [
                    'enabled' => true,
                    'sandbox' => true,
                    'cash_enabled' => false,
                    'installment_enabled' => true,
                    'fee_percent' => $fee,
                    'apply_fee_on_cash' => false,
                ],
            ],
        ])->assertOk();
    }

    private function enableOauthGateway(string $code, string $baseUrl, float $fee, bool $cash = false): void
    {
        $this->putJson('/api/v1/integrations/payments/gateways', [
            'gateways' => [
                $code => [
                    'enabled' => true,
                    'sandbox' => true,
                    'cash_enabled' => $cash,
                    'installment_enabled' => true,
                    'fee_percent' => $fee,
                    'apply_fee_on_cash' => false,
                    'base_url' => $baseUrl,
                    'client_id' => 'client-id',
                    'client_secret' => 'client-secret',
                    'username' => 'merchant',
                    'password' => 'secret-pass',
                ],
            ],
        ])->assertOk();
    }

    private function fakeSnappStyle(string $base): void
    {
        Http::fake([
            $base.'/*' => function ($request) use ($base) {
                $url = $request->url();
                if (str_contains($url, '/oauth/token')) {
                    return Http::response(['access_token' => 'access-token', 'expires_in' => 3600], 200);
                }
                if (str_contains($url, '/eligible')) {
                    return Http::response(['successful' => true, 'response' => ['eligible' => true, 'title_message' => 'ok']], 200);
                }
                if (str_contains($url, '/payment/v1/token')) {
                    return Http::response(['successful' => true, 'response' => [
                        'paymentToken' => 'pay-token',
                        'paymentPageUrl' => $base.'/pay/pay-token',
                    ]], 200);
                }
                if (str_contains($url, '/verify') || str_contains($url, '/settle')) {
                    return Http::response(['successful' => true, 'response' => ['transactionId' => 'tx-1']], 200);
                }

                return Http::response(['url' => $url], 500);
            },
        ]);
    }

    private function startInstallment(string $gateway, int $invoiceId): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/v1/integrations/payments/intents', [
            'payable_type' => 'sales_invoice',
            'payable_id' => (string) $invoiceId,
            'mode' => 'installment',
            'gateway' => $gateway,
            'mobile' => '09120000000',
        ])->assertCreated();
    }

    /**
     * @param  array<string, scalar>  $replace
     */
    private function callbackQuery(string $redirectUrl, array $replace = []): string
    {
        $parts = parse_url($redirectUrl);
        parse_str($parts['query'] ?? '', $query);
        foreach ($replace as $key => $value) {
            $query[$key] = $value;
        }
        $path = $parts['path'] ?? '';

        return $path.'?'.http_build_query($query);
    }

    /**
     * @param  array<string, scalar>  $extra
     */
    private function callbackProvider(string $gateway, string $publicId, array $extra): \Illuminate\Testing\TestResponse
    {
        $intent = PaymentIntent::query()->where('public_id', $publicId)->firstOrFail();
        $sig = app(PaymentCallbackSigner::class)->sign($intent);

        return $this->postJson('/api/v1/integrations/payments/callback/'.$gateway, array_merge([
            'pt' => $intent->public_id,
            'sig' => $sig,
            'transactionId' => $intent->provider_tx,
        ], $extra));
    }
}
