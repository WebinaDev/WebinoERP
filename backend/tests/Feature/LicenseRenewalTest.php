<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Entities\CoreHostingSetting;
use Modules\Core\Entities\CoreLicense;
use Modules\Core\Entities\CoreLicenseRenewal;
use Modules\Core\Entities\CoreLicenseRenewalPrice;
use Modules\Core\Entities\SystemModule;
use Modules\Core\Events\LicenseRenewed;
use Modules\Core\Services\LicenseRenewal\LicenseRenewalService;
use Modules\Crm\Entities\CrmAccount;
use Modules\Integrations\Entities\PaymentIntent;
use Modules\Sales\Entities\SalesInvoice;
use Modules\SiteBuilder\Database\Seeders\SiteBuilderSeeder;
use Modules\SiteBuilder\Entities\WebinoPackage;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class LicenseRenewalTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        SystemModule::query()->firstOrCreate(['slug' => 'integrations'], ['name' => 'Integrations', 'is_active' => true]);
        SystemModule::query()->firstOrCreate(['slug' => 'platform'], ['name' => 'Platform', 'is_active' => true]);
        $this->seed(SiteBuilderSeeder::class);
        CoreHostingSetting::query()->delete();
        (new CoreHostingSetting)->forceFill(['platform_base_domain' => 'webinaagency.ir', 'provision_webhook_secret' => 'test-secret'])->save();
        Cache::flush();
        Sanctum::actingAs($this->actingAsRole('system_manager'));
        $this->putJson('/api/v1/integrations/payments/gateways', ['gateways' => ['zarinpal' => [
            'enabled' => true, 'sandbox' => true, 'cash_enabled' => true, 'installment_enabled' => false,
            'fee_percent' => 0, 'apply_fee_on_cash' => false,
        ]]])->assertOk();
    }

    private function license(array $attrs = []): CoreLicense
    {
        return CoreLicense::query()->create(array_merge([
            'license_key' => 'LK-'.uniqid(), 'project_name' => 'Shop', 'domain' => 'shop.example.ir',
            'product' => 'webinodashboard', 'status' => 'active', 'expires_at' => now()->addDays(5),
            'meta' => ['plan' => 'pro'],
        ], $attrs));
    }

    private function prices(): array
    {
        $a = $this->postJson('/api/v1/core/license-renewal/prices', ['product' => 'webinodashboard', 'months' => 1, 'price_irr' => 1_000_000])->assertCreated();
        $b = $this->postJson('/api/v1/core/license-renewal/prices', ['product' => 'webinodashboard', 'months' => 12, 'price_irr' => 10_000_000, 'discount_percent' => 15, 'label' => 'سالانه'])->assertCreated();
        $c = $this->postJson('/api/v1/core/license-renewal/prices', ['product' => 'webinodashboard', 'plan' => 'pro', 'months' => 12, 'price_irr' => 20_000_000, 'discount_percent' => 10])->assertCreated();

        return [$a->json('data.id'), $b->json('data.id'), $c->json('data.id')];
    }

    private function site(CoreLicense $license, array $attrs = []): WebinoSiteProvision
    {
        $crm = CrmAccount::query()->create(['name' => 'ACME Shop', 'type' => 'customer']);

        return WebinoSiteProvision::query()->create(array_merge([
            'package_id' => WebinoPackage::query()->value('id'), 'slug' => 'shop', 'domain' => $license->domain,
            'status' => WebinoSiteProvision::STATUS_READY, 'license_id' => $license->id, 'crm_account_id' => $crm->id,
            'wizard_payload' => ['site_name' => 'Shop', 'admin_email' => 'owner@example.ir'], 'provision_token' => 'tok-shop',
        ], $attrs));
    }

    /** Starts checkout through the signed pay link and returns the gateway redirect URL. */
    private function checkout(CoreLicense $license, int $optionId, ?string $return = null): string
    {
        $info = $this->getJson('/api/v1/public/license-renewal?domain='.$license->domain.($return ? '&return_url='.urlencode($return) : ''))->assertOk();
        $opt = collect($info->json('data.options'))->firstWhere('id', $optionId);
        $this->assertNotNull($opt);
        $res = $this->get($opt['checkout_url'])->assertRedirect();

        return (string) $res->headers->get('Location');
    }

    private function gatewayCallback(string $redirect, array $replace = []): \Illuminate\Testing\TestResponse
    {
        $parts = parse_url($redirect);
        parse_str($parts['query'] ?? '', $q);

        return $this->getJson(($parts['path'] ?? '').'?'.http_build_query(array_merge($q, $replace)));
    }

    public function test_admin_pricing_crud_and_plan_specific_options_with_discount(): void
    {
        [$monthly, $yearly, $pro] = $this->prices();
        $this->getJson('/api/v1/core/license-renewal/prices')->assertOk()->assertJsonCount(3, 'data');
        $this->patchJson("/api/v1/core/license-renewal/prices/{$monthly}", ['price_irr' => 1_200_000])->assertOk();

        $options = app(LicenseRenewalService::class)->options($this->license());
        $this->assertSame([1, 12], $options->pluck('months')->all());
        $this->assertSame($pro, $options->firstWhere('months', 12)->id, 'plan row overrides catch-all');
        $this->assertSame(18_000_000, $options->firstWhere('months', 12)->finalIrr());
        $this->assertSame(1_200_000, $options->firstWhere('months', 1)->finalIrr());

        $this->deleteJson("/api/v1/core/license-renewal/prices/{$pro}")->assertNoContent();
        $this->assertSame(8_500_000, app(LicenseRenewalService::class)->options($this->license(['domain' => 'b.example.ir']))->firstWhere('months', 12)->finalIrr());

        $this->getJson('/api/v1/core/license-renewal/settings')->assertOk()
            ->assertJsonPath('data.reminders.in_app', true)->assertJsonPath('data.reminders.sms', false);
        $this->putJson('/api/v1/core/license-renewal/settings', ['reminders' => ['sms' => true, 'days' => [10, 2]]])->assertOk()
            ->assertJsonPath('data.reminders.sms', true);
        $this->assertTrue(LicenseRenewalService::settings()['reminders']['sms']);
    }

    public function test_public_info_by_domain_has_no_pii_and_filters_return_url(): void
    {
        $this->prices();
        $license = $this->license();
        $this->site($license);

        $res = $this->getJson('/api/v1/public/license-renewal?domain=WWW.Shop.Example.ir&return_url='.urlencode('https://shop.example.ir/suspended'))
            ->assertOk()
            ->assertJsonPath('data.status', 'expiring')
            ->assertJsonPath('data.plan.code', 'pro')
            ->assertJsonPath('data.renewable', true)
            ->assertJsonCount(2, 'data.options');
        $body = $res->getContent();
        foreach (['ACME', 'owner@example.ir', $license->license_key, 'crm_account', 'customer'] as $needle) {
            $this->assertStringNotContainsString($needle, $body);
        }
        $this->assertStringContainsString(urlencode('https://shop.example.ir/suspended'), (string) $res->json('data.renew_url'));

        $evil = $this->getJson('/api/v1/public/license-renewal?domain=shop.example.ir&return_url='.urlencode('https://evil.test/x'))->assertOk();
        $this->assertStringNotContainsString('evil.test', $evil->getContent());

        $this->getJson('/api/v1/public/license-renewal?domain=nope.example.ir')->assertNotFound();
        $this->getJson('/api/v1/public/license-renewal')->assertStatus(422);
    }

    public function test_signed_url_valid_tampered_and_expired(): void
    {
        $this->prices();
        $license = $this->license();
        $url = app(LicenseRenewalService::class)->renewUrl($license);

        $this->get($url)->assertOk()->assertSee($license->domain);
        $other = $this->license(['domain' => 'other.example.ir']);
        $this->get(str_replace('/renew/'.$license->id, '/renew/'.$other->id, $url))->assertForbidden();
        $this->get($url.'&x=1')->assertForbidden();

        Carbon::setTestNow(now()->addDays(8));
        $this->get($url)->assertForbidden();
        Carbon::setTestNow();
    }

    public function test_successful_callback_extends_license_creates_paid_invoice_and_reactivates_site(): void
    {
        Event::fake([LicenseRenewed::class, \Modules\Crm\Events\Customer\PaymentRecorded::class]);
        Http::fake(['*' => Http::response(['ok' => true, 'status' => 'active'])]);
        [, , $pro] = $this->prices();
        $expires = now()->subDays(2)->startOfMinute();
        $license = $this->license(['expires_at' => $expires, 'status' => 'expired']);
        $site = $this->site($license, ['site_status' => WebinoSiteProvision::SITE_SUSPENDED, 'site_status_reason' => WebinoSiteProvision::REASON_LICENSE_EXPIRED]);

        $redirect = $this->checkout($license, $pro, 'https://shop.example.ir/suspended');
        $this->assertStringContainsString('Status=OK', $redirect);
        $this->gatewayCallback($redirect)->assertOk()->assertJsonPath('data.status', 'settled');

        $license->refresh();
        $this->assertSame('active', $license->status);
        $this->assertTrue(Carbon::parse($license->expires_at)->isSameDay(now()->addMonths(12)));
        $renewal = CoreLicenseRenewal::query()->sole();
        $this->assertSame('paid', $renewal->status);
        $invoice = SalesInvoice::query()->findOrFail($renewal->sales_invoice_id);
        $this->assertSame('paid', $invoice->status);
        $this->assertSame((int) $site->crm_account_id, (int) $invoice->customer_id);
        $this->assertEquals(18_000_000, (float) $invoice->total);
        $this->assertSame(WebinoSiteProvision::SITE_ACTIVE, $site->fresh()->site_status);
        Event::assertDispatched(LicenseRenewed::class);
        Event::assertDispatched(\Modules\Crm\Events\Customer\PaymentRecorded::class);

        // Idempotent: replaying the callback does nothing more.
        $this->gatewayCallback($redirect);
        $this->assertSame(1, SalesInvoice::query()->count());
        $this->assertTrue(Carbon::parse($license->fresh()->expires_at)->isSameDay(now()->addMonths(12)));
        app(LicenseRenewalService::class)->markPaid($renewal->id);
        $this->assertSame(1, SalesInvoice::query()->count());
        Event::assertDispatchedTimes(LicenseRenewed::class, 1);
    }

    public function test_failed_callback_leaves_license_untouched_and_retry_works(): void
    {
        Http::fake(['*' => Http::response(['ok' => true])]);
        [$monthly] = $this->prices();
        $expires = now()->addDays(3)->startOfMinute();
        $license = $this->license(['expires_at' => $expires]);

        $redirect = $this->checkout($license, $monthly);
        $this->gatewayCallback($redirect, ['Status' => 'NOK'])->assertStatus(422);
        $this->assertSame('failed', PaymentIntent::query()->latest('id')->value('status'));
        $this->assertTrue(Carbon::parse($license->fresh()->expires_at)->equalTo($expires));
        $this->assertSame(0, SalesInvoice::query()->count());
        $this->assertNotSame('paid', CoreLicenseRenewal::query()->latest('id')->value('status'));

        $retry = $this->checkout($license, $monthly);
        $this->gatewayCallback($retry)->assertOk()->assertJsonPath('data.status', 'settled');
        $this->assertTrue(Carbon::parse($license->fresh()->expires_at)->isSameDay($expires->copy()->addMonth()));
    }

    public function test_reminders_send_in_app_once_with_renewal_link(): void
    {
        $this->prices();
        $owner = \App\Models\User::query()->firstOrFail();
        $license = $this->license(['expires_at' => now()->addDays(3)->setTime(12, 0), 'created_by' => $owner->id]);

        $this->artisan('core:license-renewal-reminders')->assertSuccessful();
        $this->artisan('core:license-renewal-reminders')->assertSuccessful();
        $rows = \Modules\Core\Entities\CoreNotification::query()->where('type', 'license.expiring')->get();
        $this->assertCount(1, $rows);
        $this->assertStringContainsString('/renew/'.$license->id, json_encode($rows->first()->data, JSON_UNESCAPED_SLASHES));
    }
}
