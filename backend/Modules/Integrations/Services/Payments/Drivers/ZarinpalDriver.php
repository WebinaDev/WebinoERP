<?php

namespace Modules\Integrations\Services\Payments\Drivers;

use Modules\Integrations\Entities\PaymentIntent;
use Modules\Integrations\Services\Payments\GatewayHttp;
use Modules\Integrations\Services\Payments\GatewayResult;
use Modules\Integrations\Services\Payments\PaymentGatewayDriver;

class ZarinpalDriver implements PaymentGatewayDriver
{
    public function __construct(private GatewayHttp $http) {}

    public function code(): string
    {
        return 'zarinpal';
    }

    public function requestPayment(PaymentIntent $intent, array $config, string $callbackUrl): GatewayResult
    {
        if (! empty($config['local_simulation'])) {
            $authority = 'A'.strtoupper(bin2hex(random_bytes(16)));
            $join = str_contains($callbackUrl, '?') ? '&' : '?';

            return GatewayResult::success(
                'sandbox',
                $authority,
                $callbackUrl.$join.'Authority='.$authority.'&Status=OK',
            );
        }

        $merchant = trim((string) ($config['merchant_id'] ?? ''));
        if ($merchant === '') {
            return GatewayResult::failure('شناسه پذیرنده زرین‌پال خالی است.');
        }
        $base = $this->apiBase($config);
        $res = $this->http->postJson($base.'request.json', [
            'merchant_id' => $merchant,
            'amount' => (int) $intent->total_amount,
            'currency' => 'IRR',
            'callback_url' => $callbackUrl,
            'description' => mb_substr((string) ($intent->description ?: 'پرداخت وبینو'), 0, 255),
            'metadata' => array_filter([
                'mobile' => $intent->mobile,
                'order_id' => $intent->public_id,
            ]),
        ]);
        $code = (int) ($res['json']['data']['code'] ?? 0);
        $authority = (string) ($res['json']['data']['authority'] ?? '');
        if ($code !== 100 || $authority === '') {
            $message = (string) ($res['json']['errors']['message'] ?? $res['json']['data']['message'] ?? 'درخواست زرین‌پال ناموفق بود.');

            return GatewayResult::failure($message, $res['json']);
        }

        return GatewayResult::success('ok', $authority, $this->startPay($config).$authority, '', $res['json']);
    }

    public function verifyPayment(PaymentIntent $intent, array $config, array $callback): GatewayResult
    {
        $status = strtoupper((string) ($callback['Status'] ?? $callback['status'] ?? ''));
        if ($status === 'NOK') {
            return GatewayResult::failure('پرداخت توسط کاربر لغو شد.');
        }
        if (! empty($config['local_simulation'])) {
            if ($status !== '' && $status !== 'OK') {
                return GatewayResult::failure('وضعیت بازگشت زرین‌پال نامعتبر است.');
            }
            $ref = 'ZP'.substr(sha1((string) $intent->public_id), 0, 10);

            return GatewayResult::success('sandbox-verified', (string) $intent->authority, '', $ref);
        }

        $merchant = trim((string) ($config['merchant_id'] ?? ''));
        $authority = (string) ($callback['Authority'] ?? $callback['authority'] ?? $intent->authority);
        $res = $this->http->postJson($this->apiBase($config).'verify.json', [
            'merchant_id' => $merchant,
            'amount' => (int) $intent->total_amount,
            'authority' => $authority,
        ]);
        $code = (int) ($res['json']['data']['code'] ?? 0);
        if (! in_array($code, [100, 101], true)) {
            return GatewayResult::failure('تأیید زرین‌پال ناموفق بود.', $res['json']);
        }
        $ref = (string) ($res['json']['data']['ref_id'] ?? '');

        return GatewayResult::success($code === 101 ? 'already-verified' : 'verified', $authority, '', $ref, $res['json']);
    }

    public function settlePayment(PaymentIntent $intent, array $config): GatewayResult
    {
        return GatewayResult::success('zarinpal-settle-not-required', (string) $intent->authority, '', (string) $intent->provider_ref);
    }

    public function cancelPayment(PaymentIntent $intent, array $config): GatewayResult
    {
        return GatewayResult::success('cancelled-locally');
    }

    public function revertPayment(PaymentIntent $intent, array $config): GatewayResult
    {
        return GatewayResult::failure('برگشت وجه زرین‌پال از این مسیر پشتیبانی نمی‌شود.');
    }

    public function checkEligible(int $amount, array $config): array
    {
        return ['eligible' => $amount >= 10000, 'title' => 'زرین‌پال', 'description' => 'پرداخت نقدی با کارت'];
    }

    public function testConnection(array $config): array
    {
        if (! empty($config['local_simulation'])) {
            return ['ok' => true, 'message' => 'شبیه‌ساز زرین‌پال آماده است. برای سندباکس واقعی شناسه ۳۶ کاراکتری وارد کنید.'];
        }
        $merchant = trim((string) ($config['merchant_id'] ?? ''));
        if ($merchant === '') {
            return ['ok' => false, 'message' => 'شناسه پذیرنده خالی است.'];
        }
        $res = $this->http->postJson($this->apiBase($config).'request.json', [
            'merchant_id' => $merchant,
            'amount' => 10000,
            'currency' => 'IRR',
            'callback_url' => rtrim((string) ($config['callback_base_url'] ?: url('/')), '/').'/api/v1/integrations/payments/callback/zarinpal',
            'description' => 'Webino connection test',
        ]);
        $code = (int) ($res['json']['data']['code'] ?? 0);
        if ($code === 100) {
            return ['ok' => true, 'message' => 'اتصال زرین‌پال برقرار است.'];
        }

        return ['ok' => false, 'message' => 'زرین‌پال درخواست آزمایشی را نپذیرفت.'];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function apiBase(array $config): string
    {
        $override = trim((string) ($config['base_url'] ?? ''));
        if ($override !== '') {
            return rtrim($override, '/').'/';
        }
        $urls = is_array($config['urls'] ?? null) ? $config['urls'] : [];
        $key = ! empty($config['sandbox']) ? 'sandbox' : 'production';

        return (string) ($urls[$key] ?? 'https://api.zarinpal.com/pg/v4/payment/');
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function startPay(array $config): string
    {
        $urls = is_array($config['urls'] ?? null) ? $config['urls'] : [];
        $key = ! empty($config['sandbox']) ? 'start_sandbox' : 'start_production';

        return (string) ($urls[$key] ?? 'https://www.zarinpal.com/pg/StartPay/');
    }
}
