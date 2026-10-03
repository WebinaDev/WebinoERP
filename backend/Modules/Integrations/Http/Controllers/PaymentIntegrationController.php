<?php

namespace Modules\Integrations\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\Integrations\Entities\IntegrationSetting;
use Modules\Integrations\Entities\PaymentIntent;
use Modules\Integrations\Services\Payments\PaymentException;
use Modules\Integrations\Services\Payments\PaymentOrchestrator;

/**
 * Backward-compatible initiate/verify. When a gateway is enabled in
 * settings, the call goes through the unified payment orchestrator.
 * Otherwise the original Zarinpal sandbox skeleton is kept.
 */
class PaymentIntegrationController extends Controller
{
    private const INTEGRATION = 'payment';

    private const KEY_SETTINGS = 'zarinpal';

    public function __construct(private PaymentOrchestrator $payments) {}

    public function initiate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0',
            'callback_url' => 'nullable|url',
            'description' => 'nullable|string|max:500',
            'mode' => 'nullable|string|in:cash,installment',
            'gateway' => 'nullable|string|max:32',
            'mobile' => 'nullable|string|max:20',
        ]);

        $mode = $data['mode'] ?? 'cash';
        $gateway = $data['gateway'] ?? null;
        try {
            $intent = $this->payments->start([
                'payable_type' => 'generic',
                'payable_id' => (string) Str::uuid(),
                'amount' => $data['amount'],
                'mode' => $mode,
                'gateway' => $gateway,
                'description' => $data['description'] ?? 'Payment',
                'return_url' => $data['callback_url'] ?? null,
                'mobile' => $data['mobile'] ?? null,
            ], $request->user()?->id);

            return response()->json(['data' => $this->legacyShape($intent)]);
        } catch (PaymentException $e) {
            $fallback = ($gateway === null || $gateway === 'zarinpal')
                && $mode === 'cash'
                && in_array($e->errorCode, ['no_gateway', 'gateway_disabled', 'mode_not_allowed'], true);
            if (! $fallback) {
                return response()->json(['message' => $e->getMessage(), 'error' => $e->errorCode], $e->status);
            }
        }

        return $this->legacyInitiate($request, $data);
    }

    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'authority' => 'nullable|string',
            'Authority' => 'nullable|string',
            'status' => 'nullable|string',
            'Status' => 'nullable|string',
            'ref_id' => 'nullable|string',
            'payment_id' => 'nullable|string',
            'state' => 'nullable|string',
            'sig' => 'nullable|string',
        ]);

        $authority = $data['authority'] ?? $data['Authority'] ?? null;
        $intent = null;
        if (! empty($data['payment_id'])) {
            $intent = PaymentIntent::query()->where('public_id', $data['payment_id'])->first();
        }
        if (! $intent && is_string($authority) && $authority !== '') {
            $intent = PaymentIntent::query()->where('authority', $authority)->first();
        }
        if ($intent) {
            $response = $this->payments->finish($intent, $request->all(), true);
            $payload = $response->getData(true);
            $row = is_array($payload['data'] ?? null) ? $payload['data'] : [];
            $verified = ($row['status'] ?? '') === 'settled';

            return response()->json([
                'data' => array_merge($row, [
                    'verified' => $verified,
                    'ref_id' => $row['provider_ref'] ?? ($data['ref_id'] ?? null),
                    'payment_id' => $row['payment_id'] ?? null,
                    'amount' => $row['total_amount'] ?? $row['amount'] ?? null,
                ]),
                'message' => $payload['message'] ?? null,
            ], $response->status());
        }

        $payload = $authority ? Cache::pull('payment:'.$authority) : null;
        $verified = $payload !== null && ($data['status'] ?? $data['Status'] ?? '') !== 'NOK';

        return response()->json([
            'data' => [
                'verified' => $verified,
                'ref_id' => $data['ref_id'] ?? ($verified ? (string) random_int(100000, 999999) : null),
                'payment_id' => $payload['payment_id'] ?? null,
                'amount' => $payload['amount'] ?? null,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function legacyInitiate(Request $request, array $data): JsonResponse
    {
        $settings = IntegrationSetting::getJson(self::INTEGRATION, self::KEY_SETTINGS, []);
        $merchantId = $settings['merchant_id'] ?? env('ZARINPAL_MERCHANT_ID', 'sandbox');
        $sandbox = $merchantId === 'sandbox' || (bool) ($settings['sandbox'] ?? env('ZARINPAL_SANDBOX', true));
        $base = $sandbox ? 'https://sandbox.zarinpal.com/pg/v4/payment/' : 'https://api.zarinpal.com/pg/v4/payment/';
        $callback = $data['callback_url'] ?? url('/payment/callback');
        $paymentId = 'pay_'.Str::uuid()->toString();

        if ($merchantId !== 'sandbox' && ! $sandbox) {
            $res = Http::asJson()->post($base.'request.json', [
                'merchant_id' => $merchantId,
                'amount' => (int) round((float) $data['amount']),
                'callback_url' => $callback,
                'description' => $data['description'] ?? 'Payment',
            ]);
            $json = $res->json();
            if (($json['data']['code'] ?? 0) !== 100) {
                return response()->json(['message' => $json['errors'] ?? $json], 422);
            }
            $authority = (string) ($json['data']['authority'] ?? '');
            Cache::put('payment:'.$authority, [
                'payment_id' => $paymentId,
                'amount' => (float) $data['amount'],
                'merchant_id' => $merchantId,
                'user_id' => $request->user()?->id,
            ], now()->addHours(1));
            $redirectUrl = ($sandbox ? 'https://sandbox.zarinpal.com/pg/StartPay/' : 'https://www.zarinpal.com/pg/StartPay/').$authority;

            return response()->json([
                'data' => [
                    'payment_id' => $paymentId,
                    'authority' => $authority,
                    'redirect_url' => $redirectUrl,
                    'merchant_id' => $merchantId,
                ],
            ]);
        }

        $authority = 'A'.Str::upper(Str::random(31));
        Cache::put('payment:'.$authority, [
            'payment_id' => $paymentId,
            'amount' => (float) $data['amount'],
            'merchant_id' => $merchantId,
            'user_id' => $request->user()?->id,
        ], now()->addHours(1));
        $redirectUrl = $callback.(str_contains($callback, '?') ? '&' : '?').'Authority='.$authority;

        return response()->json([
            'data' => [
                'payment_id' => $paymentId,
                'authority' => $authority,
                'redirect_url' => $redirectUrl,
                'merchant_id' => $merchantId,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function legacyShape(PaymentIntent $intent): array
    {
        return array_merge($intent->toApiArray(), [
            'merchant_id' => $intent->gateway,
        ]);
    }
}
