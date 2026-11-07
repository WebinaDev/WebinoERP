<?php

namespace Modules\Integrations\Services\Payments\Drivers;

use Modules\Integrations\Entities\PaymentIntent;
use Modules\Integrations\Services\Payments\CommerceDrivers\GatewayDriverInterface;
use Modules\Integrations\Services\Payments\GatewayResult;
use Modules\Integrations\Services\Payments\PaymentGatewayDriver;

/**
 * Adapts Dashboard-style commerce drivers (request/verify) to the ERP PaymentGatewayDriver contract.
 */
class CommerceDriverAdapter implements PaymentGatewayDriver
{
    public function __construct(private GatewayDriverInterface $inner) {}

    public function code(): string
    {
        return $this->inner->code();
    }

    public function requestPayment(PaymentIntent $intent, array $config, string $callbackUrl): GatewayResult
    {
        if (! empty($config['local_simulation'])) {
            $authority = strtoupper($this->code()).'-'.strtoupper(bin2hex(random_bytes(8)));
            $join = str_contains($callbackUrl, '?') ? '&' : '?';

            return GatewayResult::success(
                'sandbox',
                $authority,
                $callbackUrl.$join.'Authority='.urlencode($authority).'&Status=OK&status=success',
            );
        }

        $amountRial = (int) $intent->total_amount;
        $result = $this->inner->request(
            $config,
            $amountRial,
            $callbackUrl,
            (string) ($intent->description ?: ('پرداخت '.$intent->public_id)),
            [
                'order_id' => (string) $intent->public_id,
                'mobile' => $intent->mobile,
            ]
        );
        if (! ($result['ok'] ?? false) || empty($result['redirect_url'])) {
            return GatewayResult::failure((string) ($result['message'] ?? 'شروع پرداخت ناموفق بود.'), is_array($result['meta'] ?? null) ? $result['meta'] : []);
        }

        $meta = is_array($result['meta'] ?? null) ? $result['meta'] : [];
        $redirect = (string) $result['redirect_url'];
        if ($redirect === 'form-bridge' || ! empty($meta['form_action'])) {
            $meta['form_action'] = (string) ($meta['form_action'] ?? '');
            $meta['form_fields'] = is_array($meta['form_fields'] ?? null) ? $meta['form_fields'] : [];
            $meta['form_method'] = (string) ($meta['form_method'] ?? 'POST');
            $redirect = url('/api/v1/integrations/payments/bridge/'.$intent->public_id);
        }

        return GatewayResult::success(
            (string) ($result['message'] ?? 'ok'),
            (string) ($result['authority'] ?? ''),
            $redirect,
            '',
            $meta,
        );
    }

    public function verifyPayment(PaymentIntent $intent, array $config, array $callback): GatewayResult
    {
        if (! empty($config['local_simulation'])) {
            $status = strtoupper((string) ($callback['Status'] ?? $callback['status'] ?? 'OK'));
            if (in_array($status, ['NOK', 'FAILED', 'CANCEL', 'CANCELED'], true)) {
                return GatewayResult::failure('پرداخت توسط کاربر لغو شد.');
            }

            return GatewayResult::success(
                'sandbox-verified',
                (string) $intent->authority,
                '',
                'SIM-'.substr(sha1((string) $intent->public_id), 0, 10),
            );
        }

        $meta = is_array($intent->provider_payload) ? $intent->provider_payload : [];
        $result = $this->inner->verify($config, (int) $intent->total_amount, $callback, $meta);
        if (! ($result['ok'] ?? false)) {
            return GatewayResult::failure((string) ($result['message'] ?? 'تأیید پرداخت ناموفق بود.'), is_array($result['meta'] ?? null) ? $result['meta'] : []);
        }

        return GatewayResult::success(
            (string) ($result['message'] ?? 'verified'),
            (string) $intent->authority,
            '',
            (string) ($result['ref_id'] ?? ''),
            is_array($result['meta'] ?? null) ? $result['meta'] : [],
        );
    }

    public function settlePayment(PaymentIntent $intent, array $config): GatewayResult
    {
        return GatewayResult::success('settle-not-required', (string) $intent->authority, '', (string) $intent->provider_ref);
    }

    public function cancelPayment(PaymentIntent $intent, array $config): GatewayResult
    {
        return GatewayResult::success('cancelled-locally');
    }

    public function revertPayment(PaymentIntent $intent, array $config): GatewayResult
    {
        return GatewayResult::success('revert-not-required');
    }

    public function checkEligible(int $amount, array $config): array
    {
        return [
            'eligible' => $amount > 0,
            'title' => (string) ($config['label_fa'] ?? $this->code()),
            'description' => $amount > 0 ? '' : 'مبلغ باید بیشتر از صفر باشد.',
        ];
    }

    public function testConnection(array $config): array
    {
        if (! empty($config['local_simulation'])) {
            return ['ok' => true, 'message' => 'حالت شبیه‌ساز محلی فعال است.'];
        }

        return $this->inner->testConnection($config);
    }
}
