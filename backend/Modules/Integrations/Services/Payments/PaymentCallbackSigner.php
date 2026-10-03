<?php

namespace Modules\Integrations\Services\Payments;

use Modules\Integrations\Entities\PaymentIntent;

class PaymentCallbackSigner
{
    public function secret(): string
    {
        $secret = (string) config('payment_gateways.callback_secret', '');
        if ($secret !== '') {
            return $secret;
        }

        return (string) config('app.key', '');
    }

    public function sign(PaymentIntent $intent): string
    {
        $payload = $intent->public_id.'|'.$intent->callback_token.'|'.$intent->total_amount;

        return hash_hmac('sha256', $payload, $this->secret());
    }

    public function matches(?string $sig, PaymentIntent $intent): bool
    {
        if ($sig === null || $sig === '') {
            return false;
        }

        return hash_equals($this->sign($intent), $sig);
    }

    public function callbackUrl(PaymentIntent $intent, ?string $baseOverride = null): string
    {
        $base = rtrim((string) ($baseOverride ?: config('payment_gateways.callback_base_url') ?: url('')), '/');
        $query = http_build_query([
            'pt' => $intent->public_id,
            'sig' => $this->sign($intent),
        ]);

        return $base.'/api/v1/integrations/payments/callback/'.$intent->gateway.'?'.$query;
    }

    public function returnUrl(PaymentIntent $intent): ?string
    {
        $return = trim((string) $intent->return_url);
        if ($return === '') {
            return null;
        }
        $status = (string) $intent->status;
        $payload = $intent->public_id.'|'.$status.'|'.$intent->total_amount;
        $query = http_build_query([
            'payment_id' => $intent->public_id,
            'status' => $status,
            'gateway' => $intent->gateway,
            'mode' => $intent->mode,
            'amount' => $intent->total_amount,
            'base_amount' => $intent->base_amount,
            'fee_amount' => $intent->fee_amount,
            'fee_percent' => $intent->fee_percent,
            'ref' => (string) ($intent->provider_ref ?? ''),
            'sig' => hash_hmac('sha256', $payload, $this->secret()),
        ]);
        $join = str_contains($return, '?') ? '&' : '?';

        return $return.$join.$query;
    }
}
