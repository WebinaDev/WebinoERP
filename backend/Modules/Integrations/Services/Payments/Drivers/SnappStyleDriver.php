<?php

namespace Modules\Integrations\Services\Payments\Drivers;

use Illuminate\Support\Facades\Cache;
use Modules\Integrations\Entities\PaymentIntent;
use Modules\Integrations\Services\Payments\GatewayHttp;
use Modules\Integrations\Services\Payments\GatewayResult;
use Modules\Integrations\Services\Payments\PaymentGatewayDriver;

/**
 * Shared OAuth + payment token flow used by Snapp Pay and Torob Pay.
 */
abstract class SnappStyleDriver implements PaymentGatewayDriver
{
    public function __construct(protected GatewayHttp $http) {}

    abstract protected function defaultProductionBase(): string;

    public function requestPayment(PaymentIntent $intent, array $config, string $callbackUrl): GatewayResult
    {
        if (! empty($config['local_simulation'])) {
            $token = 'local-'.$intent->public_id;
            $join = str_contains($callbackUrl, '?') ? '&' : '?';

            return GatewayResult::success(
                'sandbox',
                $token,
                $callbackUrl.$join.'transactionId='.urlencode((string) $intent->provider_tx).'&state=OK&amount='.$intent->total_amount,
            );
        }

        $access = $this->accessToken($config);
        if ($access === null) {
            return GatewayResult::failure('دریافت توکن دسترسی ناموفق بود.');
        }
        $body = $this->tokenBody($intent, $callbackUrl);
        $res = $this->http->postJson($this->base($config).'/api/online/payment/v1/token', $body, [], $access);
        $token = (string) ($res['json']['response']['paymentToken'] ?? $res['json']['paymentToken'] ?? '');
        $page = (string) ($res['json']['response']['paymentPageUrl'] ?? $res['json']['paymentPageUrl'] ?? '');
        $ok = $res['ok'] && $token !== '' && ($res['json']['successful'] ?? true) !== false;
        if (! $ok || $page === '') {
            return GatewayResult::failure('دریافت توکن پرداخت ناموفق بود.', $res['json']);
        }

        return GatewayResult::success('ok', $token, $page, '', $res['json']);
    }

    public function verifyPayment(PaymentIntent $intent, array $config, array $callback): GatewayResult
    {
        $state = strtoupper((string) ($callback['state'] ?? $callback['State'] ?? $callback['status'] ?? $callback['Status'] ?? ''));
        if (in_array($state, ['FAILED', 'NOK', 'CANCEL', 'CANCELED'], true)) {
            return GatewayResult::failure('پرداخت اقساطی ناموفق یا لغو شد.');
        }
        $callbackAmount = $callback['amount'] ?? null;
        if ($callbackAmount !== null && $callbackAmount !== '' && (int) $callbackAmount !== (int) $intent->total_amount) {
            return GatewayResult::failure('مبلغ بازگشت با مبلغ ذخیره شده یکی نیست.');
        }
        if (! empty($config['local_simulation'])) {
            if ($state !== '' && $state !== 'OK') {
                return GatewayResult::failure('وضعیت بازگشت نامعتبر است.');
            }

            return GatewayResult::success('sandbox-verified', (string) $intent->authority, '', 'local-ref-'.$intent->id);
        }

        $access = $this->accessToken($config);
        if ($access === null) {
            return GatewayResult::failure('دریافت توکن دسترسی ناموفق بود.');
        }
        $res = $this->http->postJson($this->base($config).'/api/online/payment/v1/verify', [
            'paymentToken' => (string) $intent->authority,
        ], [], $access);
        if (! $this->successful($res)) {
            return GatewayResult::failure('تأیید پرداخت ناموفق بود.', $res['json']);
        }
        $ref = (string) ($res['json']['response']['transactionId'] ?? $intent->provider_tx ?? '');

        return GatewayResult::success('verified', (string) $intent->authority, '', $ref, $res['json']);
    }

    public function settlePayment(PaymentIntent $intent, array $config): GatewayResult
    {
        if (! empty($config['local_simulation'])) {
            return GatewayResult::success('sandbox-settled', (string) $intent->authority, '', (string) ($intent->provider_ref ?: 'local-settle'));
        }
        $access = $this->accessToken($config);
        if ($access === null) {
            return GatewayResult::failure('دریافت توکن دسترسی ناموفق بود.');
        }
        $res = $this->http->postJson($this->base($config).'/api/online/payment/v1/settle', [
            'paymentToken' => (string) $intent->authority,
        ], [], $access);
        if (! $this->successful($res)) {
            $status = $this->remoteStatus($intent, $config, $access);
            if ($status === 'SETTLE') {
                return GatewayResult::success('already-settled', (string) $intent->authority, '', (string) $intent->provider_ref, ['status' => $status]);
            }

            return GatewayResult::failure('تسویه پرداخت ناموفق بود.', $res['json']);
        }

        return GatewayResult::success('settled', (string) $intent->authority, '', (string) ($intent->provider_ref ?: $intent->provider_tx), $res['json']);
    }

    public function cancelPayment(PaymentIntent $intent, array $config): GatewayResult
    {
        return $this->postTokenAction($intent, $config, '/api/online/payment/v1/cancel', 'cancelled');
    }

    public function revertPayment(PaymentIntent $intent, array $config): GatewayResult
    {
        return $this->postTokenAction($intent, $config, '/api/online/payment/v1/revert', 'reverted');
    }

    public function checkEligible(int $amount, array $config): array
    {
        if (! empty($config['local_simulation'])) {
            $ok = $amount >= 40000 && $amount <= 1000000000;

            return [
                'eligible' => $ok,
                'title' => (string) ($config['label_fa'] ?? $this->code()),
                'description' => $ok ? 'پرداخت اقساطی در شبیه‌ساز' : 'مبلغ در بازه آزمایشی مجاز نیست.',
            ];
        }
        $access = $this->accessToken($config);
        if ($access === null) {
            return ['eligible' => false, 'title' => '', 'description' => ''];
        }
        $res = $this->http->getJson($this->base($config).'/api/online/offer/v1/eligible', [
            'amount' => $amount,
        ], [], $access);
        $response = is_array($res['json']['response'] ?? null) ? $res['json']['response'] : $res['json'];
        $eligible = (bool) ($response['eligible'] ?? false);

        return [
            'eligible' => $eligible,
            'title' => (string) ($response['title_message'] ?? $response['title'] ?? ''),
            'description' => (string) ($response['description'] ?? ''),
        ];
    }

    public function testConnection(array $config): array
    {
        if (! empty($config['local_simulation'])) {
            return ['ok' => true, 'message' => 'شبیه‌ساز آماده است. برای اتصال واقعی آدرس پایه و شناسه‌ها را وارد کنید. آی‌پی سرور باید وایت‌لیست شود.'];
        }
        $token = $this->accessToken($config, false);
        if ($token === null) {
            return ['ok' => false, 'message' => 'دریافت توکن ناموفق بود. شناسه‌ها، آدرس پایه و وایت‌لیست آی‌پی را بررسی کنید.'];
        }

        return ['ok' => true, 'message' => 'توکن دسترسی دریافت شد.'];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function accessToken(array $config, bool $useCache = true): ?string
    {
        $clientId = trim((string) ($config['client_id'] ?? ''));
        $secret = trim((string) ($config['client_secret'] ?? ''));
        $username = trim((string) ($config['username'] ?? ''));
        $password = trim((string) ($config['password'] ?? ''));
        if ($clientId === '' || $secret === '' || $username === '' || $password === '') {
            return null;
        }
        $cacheKey = 'payment:oauth:'.$this->code().':'.sha1($clientId.'|'.$this->base($config));
        if ($useCache) {
            $cached = Cache::get($cacheKey);
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        }
        $res = $this->http->postForm($this->base($config).'/api/online/v1/oauth/token', [
            'grant_type' => 'password',
            'scope' => 'online-merchant',
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
    protected function base(array $config): string
    {
        $override = trim((string) ($config['base_url'] ?? ''));
        if ($override !== '') {
            return rtrim($override, '/');
        }
        $urls = is_array($config['urls'] ?? null) ? $config['urls'] : [];
        $key = ! empty($config['sandbox']) ? 'sandbox' : 'production';
        $url = trim((string) ($urls[$key] ?? ''));
        if ($url === '') {
            $url = $this->defaultProductionBase();
        }

        return rtrim($url, '/');
    }

    /**
     * @return array<string, mixed>
     */
    protected function tokenBody(PaymentIntent $intent, string $callbackUrl): array
    {
        $amount = (int) $intent->total_amount;
        $title = mb_substr((string) ($intent->description ?: 'سفارش وبینو'), 0, 120);

        return [
            'amount' => $amount,
            'discountAmount' => 0,
            'externalSourceAmount' => 0,
            'mobile' => (string) ($intent->mobile ?: '09120000000'),
            'returnURL' => $callbackUrl,
            'transactionId' => (string) $intent->provider_tx,
            'paymentMethodTypeDto' => 'INSTALLMENT',
            'cartList' => [[
                'cartId' => (string) $intent->payable_id,
                'isShipmentIncluded' => true,
                'isTaxIncluded' => true,
                'shippingAmount' => 0,
                'taxAmount' => 0,
                'totalAmount' => $amount,
                'cartItems' => [[
                    'id' => 1,
                    'name' => $title,
                    'amount' => $amount,
                    'count' => 1,
                    'category' => (string) $intent->payable_type,
                    'commissionType' => 100,
                ]],
            ]],
        ];
    }

    /**
     * @param  array{status:int,json:array<string,mixed>,ok:bool}  $res
     */
    protected function successful(array $res): bool
    {
        if (! $res['ok']) {
            return false;
        }
        if (array_key_exists('successful', $res['json'])) {
            return (bool) $res['json']['successful'];
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function remoteStatus(PaymentIntent $intent, array $config, string $access): string
    {
        $res = $this->http->getJson($this->base($config).'/api/online/payment/v1/status', [
            'paymentToken' => (string) $intent->authority,
        ], [], $access);
        $response = is_array($res['json']['response'] ?? null) ? $res['json']['response'] : $res['json'];

        return strtoupper((string) ($response['status'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function postTokenAction(PaymentIntent $intent, array $config, string $path, string $okMessage): GatewayResult
    {
        if (! empty($config['local_simulation'])) {
            return GatewayResult::success($okMessage);
        }
        $access = $this->accessToken($config);
        if ($access === null) {
            return GatewayResult::failure('دریافت توکن دسترسی ناموفق بود.');
        }
        $res = $this->http->postJson($this->base($config).$path, [
            'paymentToken' => (string) $intent->authority,
        ], [], $access);
        if (! $this->successful($res)) {
            return GatewayResult::failure('عملیات درگاه ناموفق بود.', $res['json']);
        }

        return GatewayResult::success($okMessage, (string) $intent->authority, '', (string) $intent->provider_ref, $res['json']);
    }
}
