<?php

namespace Modules\Integrations\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\Integrations\Entities\IntegrationSetting;

/**
 * Zarinpal-compatible initiate/verify skeleton (authority stored until verify).
 */
class PaymentIntegrationController extends Controller
{
    private const INTEGRATION = 'payment';

    private const KEY_SETTINGS = 'zarinpal';

    public function initiate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0',
            'callback_url' => 'nullable|url',
            'description' => 'nullable|string|max:500',
        ]);

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

    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'authority' => 'nullable|string',
            'Authority' => 'nullable|string',
            'status' => 'nullable|string',
            'ref_id' => 'nullable|string',
        ]);

        $authority = $data['authority'] ?? $data['Authority'] ?? null;
        $payload = $authority ? Cache::pull('payment:'.$authority) : null;

        $verified = $payload !== null && ($data['status'] ?? '') !== 'NOK';

        return response()->json([
            'data' => [
                'verified' => $verified,
                'ref_id' => $data['ref_id'] ?? ($verified ? (string) random_int(100000, 999999) : null),
                'payment_id' => $payload['payment_id'] ?? null,
                'amount' => $payload['amount'] ?? null,
            ],
        ]);
    }
}
