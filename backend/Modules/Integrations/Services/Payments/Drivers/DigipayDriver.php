<?php

namespace Modules\Integrations\Services\Payments\Drivers;

use Illuminate\Support\Facades\Cache;
use Modules\Integrations\Entities\PaymentIntent;
use Modules\Integrations\Services\Payments\GatewayHttp;
use Modules\Integrations\Services\Payments\GatewayResult;
use Modules\Integrations\Services\Payments\PaymentGatewayDriver;

/**
 * Digikala Digipay merchant UPG (mydigipay), not DigiPay.Guru.
 */
class DigipayDriver implements PaymentGatewayDriver
{
    public function __construct(private GatewayHttp $http) {}

    public function code(): string
    {
        return 'digipay';
    }

    public function requestPayment(PaymentIntent $intent, array $config, string $callbackUrl): GatewayResult
    {
        if (! empty($config['local_simulation'])) {
            $ticket = 'local-'.$intent->public_id;
            $join = str_contains($callbackUrl, '?') ? '&' : '?';

            return GatewayResult::success(
                'sandbox',
                $ticket,
                $callbackUrl.$join.'trackingCode='.$ticket.'&providerId='.urlencode($intent->public_id).'&result=SUCCESS&amount='.$intent->total_amount,
            );
        }

        $access = $this->accessToken($config);
        if ($access === null) {
            return GatewayResult::failure('ورود به دیجی‌پی ناموفق بود.');
        }
        $type = $this->ticketType($intent, $config);
        $res = $this->http->postJson($this->base($config).'/tickets/business?type='.$type, [
            'cellNumber' => (string) ($intent->mobile ?: '09120000000'),
            'amount' => (int) $intent->total_amount,
            'providerId' => $intent->public_id,
            'callbackUrl' => $callbackUrl,
            'basketDetailsDto' => [
                'basketId' => (string) $intent->payable_id,
                'items' => [[
                    'sellerId' => '1',
                    'supplierId' => '1',
                    'productCode' => (string) $intent->payable_type,
                    'brand' => 'webino',
                    'productType' => 1,
                    'count' => 1,
                    'categoryId' => '1',
                ]],
            ],
        ], $this->headers(), $access);
        $redirect = (string) ($res['json']['redirectUrl'] ?? $res['json']['ticket'] ?? $res['json']['payUrl'] ?? '');
        $ticket = (string) ($res['json']['ticket'] ?? $res['json']['trackingCode'] ?? '');
        $resultStatus = $res['json']['result']['status'] ?? null;
        $ok = $res['ok'] && $redirect !== '' && ($resultStatus === null || (int) $resultStatus === 0);
        if (! $ok) {
            return GatewayResult::failure('ساخت بلیط دیجی‌پی ناموفق بود.', $res['json']);
        }

        return GatewayResult::success('ok', $ticket !== '' ? $ticket : $intent->public_id, $redirect, '', $res['json']);
    }

    public function verifyPayment(PaymentIntent $intent, array $config, array $callback): GatewayResult
    {
        $result = strtoupper((string) ($callback['result'] ?? $callback['Result'] ?? ''));
        if (in_array($result, ['FAILURE', 'FAILED', 'CANCEL', '0'], true) && $result !== '0') {
            return GatewayResult::failure('نتیجه بازگشت دیجی‌پی ناموفق است.');
        }
        if ($result === 'FAILURE' || $result === 'FAILED') {
            return GatewayResult::failure('نتیجه بازگشت دیجی‌پی ناموفق است.');
        }
        $callbackAmount = $callback['amount'] ?? null;
        if ($callbackAmount !== null && $callbackAmount !== '' && (int) $callbackAmount !== (int) $intent->total_amount) {
            return GatewayResult::failure('مبلغ بازگشت با مبلغ ذخیره شده یکی نیست.');
        }
        $tracking = (string) ($callback['trackingCode'] ?? $callback['tracking_code'] ?? $intent->authority);
        if (! empty($config['local_simulation'])) {
            if ($result !== '' && $result !== 'SUCCESS' && $result !== 'OK') {
                return GatewayResult::failure('وضعیت بازگشت دیجی‌پی نامعتبر است.');
            }

            return GatewayResult::success('sandbox-verified', $tracking, '', 'local-rrn-'.$intent->id);
        }

        $access = $this->accessToken($config);
        if ($access === null) {
            return GatewayResult::failure('ورود به دیجی‌پی ناموفق بود.');
        }
        $type = $this->ticketType($intent, $config);
        $res = $this->http->postJson($this->base($config).'/purchases/verify?type='.$type, [
            'trackingCode' => $tracking,
            'providerId' => $intent->public_id,
        ], $this->headers(), $access);
        $status = $res['json']['result']['status'] ?? null;
        if (! $res['ok'] || ($status !== null && (int) $status !== 0)) {
            return GatewayResult::failure('تأیید دیجی‌پی ناموفق بود.', $res['json']);
        }
        $ref = (string) ($res['json']['rrn'] ?? $res['json']['trackingCode'] ?? $tracking);

        return GatewayResult::success('verified', $tracking, '', $ref, $res['json']);
    }

    public function settlePayment(PaymentIntent $intent, array $config): GatewayResult
    {
        if (! empty($config['local_simulation'])) {
            return GatewayResult::success('sandbox-delivered', (string) $intent->authority, '', (string) $intent->provider_ref);
        }
        $access = $this->accessToken($config);
        if ($access === null) {
            return GatewayResult::failure('ورود به دیجی‌پی ناموفق بود.');
        }
        $type = $this->ticketType($intent, $config);
        $res = $this->http->postJson($this->base($config).'/purchases/deliver?type='.$type, [
            'trackingCode' => (string) ($intent->authority ?: $intent->provider_ref),
            'providerId' => $intent->public_id,
            'invoiceNumber' => (string) $intent->payable_id,
            'deliveryDate' => now()->getTimestampMs(),
            'products' => [(string) $intent->description],
        ], $this->headers(), $access);
        $status = $res['json']['result']['status'] ?? null;
        if (! $res['ok'] || ($status !== null && (int) $status !== 0)) {
            return GatewayResult::failure('تحویل خرید دیجی‌پی ناموفق بود.', $res['json']);
        }

        return GatewayResult::success('delivered', (string) $intent->authority, '', (string) $intent->provider_ref, $res['json']);
    }

    public function cancelPayment(PaymentIntent $intent, array $config): GatewayResult
    {
        return $this->revertPayment($intent, $config);
    }

    public function revertPayment(PaymentIntent $intent, array $config): GatewayResult
    {
        if (! empty($config['local_simulation'])) {
            return GatewayResult::success('sandbox-reversed');
        }
        $access = $this->accessToken($config);
        if ($access === null) {
            return GatewayResult::failure('ورود به دیجی‌پی ناموفق بود.');
        }
        $type = $this->ticketType($intent, $config);
        $res = $this->http->postJson($this->base($config).'/purchases/reverse?type='.$type, [
            'purchaseTrackingCode' => (string) ($intent->provider_ref ?: $intent->authority),
            'providerId' => $intent->public_id,
            'amount' => (int) $intent->total_amount,
        ], $this->headers(), $access);
        $status = $res['json']['result']['status'] ?? null;
        if (! $res['ok'] || ($status !== null && (int) $status !== 0)) {
            return GatewayResult::failure('برگشت دیجی‌پی ناموفق بود.', $res['json']);
        }

        return GatewayResult::success('reversed', (string) $intent->authority, '', (string) $intent->provider_ref, $res['json']);
    }

    public function checkEligible(int $amount, array $config): array
    {
        $ok = $amount >= 10000;

        return [
            'eligible' => $ok,
            'title' => 'دیجی‌پی',
            'description' => $ok ? 'پرداخت از طریق دیجی‌پی' : 'حداقل مبلغ ۱۰٬۰۰۰ ریال است.',
        ];
    }

    public function testConnection(array $config): array
    {
        if (! empty($config['local_simulation'])) {
            return ['ok' => true, 'message' => 'شبیه‌ساز دیجی‌پی آماده است. برای UAT شناسه‌های پذیرنده را وارد کنید.'];
        }
        $token = $this->accessToken($config, false);

        return $token
            ? ['ok' => true, 'message' => 'ورود دیجی‌پی موفق بود.']
            : ['ok' => false, 'message' => 'ورود دیجی‌پی ناموفق بود.'];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function ticketType(PaymentIntent $intent, array $config): int
    {
        if ($intent->mode === 'cash') {
            return (int) ($config['ticket_type_cash'] ?? 0);
        }

        return (int) ($config['ticket_type_installment'] ?? 13);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function accessToken(array $config, bool $useCache = true): ?string
    {
        $clientId = trim((string) ($config['client_id'] ?? ''));
        $secret = trim((string) ($config['client_secret'] ?? ''));
        $username = trim((string) ($config['username'] ?? ''));
        $password = trim((string) ($config['password'] ?? ''));
        if ($clientId === '' || $secret === '' || $username === '' || $password === '') {
            return null;
        }
        $cacheKey = 'payment:oauth:digipay:'.sha1($clientId.'|'.$this->base($config));
        if ($useCache) {
            $cached = Cache::get($cacheKey);
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        }
        $res = $this->http->postForm($this->base($config).'/oauth/token', [
            'grant_type' => 'password',
            'username' => $username,
            'password' => $password,
        ], [$clientId, $secret]);
        $token = (string) ($res['json']['access_token'] ?? '');
        if ($token === '') {
            return null;
        }
        $ttl = max(60, ((int) ($res['json']['expires_in'] ?? 3600)) - 120);
        Cache::put($cacheKey, $token, $ttl);

        return $token;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function base(array $config): string
    {
        $override = trim((string) ($config['base_url'] ?? ''));
        if ($override !== '') {
            return rtrim($override, '/');
        }
        $urls = is_array($config['urls'] ?? null) ? $config['urls'] : [];
        $key = ! empty($config['sandbox']) ? 'sandbox' : 'production';

        return rtrim((string) ($urls[$key] ?? 'https://api.mydigipay.com/digipay/api'), '/');
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return [
            'Agent' => 'WEB',
            'Digipay-Version' => '2022-02-02',
        ];
    }
}
