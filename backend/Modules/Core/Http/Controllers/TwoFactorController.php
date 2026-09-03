<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\Core\Database\Seeders\RolesAndPermissionsSeeder;
use Modules\Integrations\Entities\IntegrationSetting;
use Modules\Integrations\Http\Controllers\SmsIntegrationController;

class TwoFactorController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $required = $user->hasRole(RolesAndPermissionsSeeder::ROLE_SYSTEM_MANAGER);

        return response()->json([
            'data' => [
                'enabled' => (bool) Cache::get('2fa:verified:'.$user->id, false),
                'required_for_admin' => $required,
            ],
        ]);
    }

    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => 'required|string|size:6']);
        $user = $request->user();
        $key = 'otp_admin:'.$user->id;
        $attemptsKey = 'otp_admin_attempts:'.$user->id;
        $expected = Cache::get($key);

        if (! is_string($expected) || ! hash_equals($expected, $data['code'])) {
            $attempts = (int) Cache::increment($attemptsKey);
            if ($attempts === 1) {
                Cache::put($attemptsKey, 1, now()->addMinutes(10));
            }
            if ($attempts >= 5) {
                Cache::forget($key);
                Cache::forget($attemptsKey);
            }

            return response()->json(['message' => 'Invalid verification code'], 422);
        }

        Cache::forget($key);
        Cache::forget($attemptsKey);
        Cache::put('2fa:verified:'.$user->id, true, now()->addHours(12));

        // Upgrade token from 2fa-pending → full access.
        $request->user()?->currentAccessToken()?->delete();
        $tokenObj = $user->createToken('spa', ['*']);
        $tokenObj->accessToken->forceFill([
            'device_name' => substr((string) $request->header('X-Device-Name', 'web'), 0, 120),
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 2000),
            'last_activity_at' => now(),
        ])->save();

        $secure = (bool) config('session.secure', false);
        $response = response()->json(['data' => ['verified' => true]]);

        return $response->cookie(
            config('auth.cookie_name', 'webino_auth_token'),
            $tokenObj->plainTextToken,
            config('auth.cookie_max_minutes', 60 * 24 * 7),
            '/',
            null,
            $secure,
            true,
            false,
            'strict'
        );
    }

    public function send(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->hasRole(RolesAndPermissionsSeeder::ROLE_SYSTEM_MANAGER), 403);

        $code = (string) random_int(100000, 999999);
        Cache::put('otp_admin:'.$user->id, $code, now()->addMinutes(10));

        $delivered = false;
        $phone = $user->phone;
        if (is_string($phone) && $phone !== '') {
            $smsSettings = IntegrationSetting::getJson('sms', 'settings', []);
            $provider = $smsSettings['provider'] ?? config('integrations.sms.default', 'log');
            if ($provider !== 'disabled' && $provider !== 'stub') {
                try {
                    app(SmsIntegrationController::class)->send(new Request([
                        'to' => $phone,
                        'message' => 'کد تأیید دو مرحله‌ای: '.$code,
                    ]));
                    $delivered = true;
                } catch (\Throwable $e) {
                    Log::warning('auth.2fa.sms.failed', ['error' => $e->getMessage()]);
                }
            }
        }

        Log::info('auth.2fa.sent', [
            'user_id' => $user->id,
            'delivered' => $delivered,
        ]);

        $payload = ['sent' => true, 'delivered' => $delivered];
        $message = $delivered ? 'Verification code sent' : 'Verification code generated';
        if (config('app.debug') && app()->environment('local')) {
            $payload['debug_code'] = $code;
            $message = "Dev code: {$code}";
        }

        return response()->json([
            'data' => $payload,
            'message' => $message,
        ]);
    }
}
