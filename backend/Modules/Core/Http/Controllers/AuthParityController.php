<?php

namespace Modules\Core\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Integrations\Entities\IntegrationSetting;
use Modules\Core\Database\Seeders\RolesAndPermissionsSeeder;
use Modules\Integrations\Http\Controllers\SmsIntegrationController;

/**
 * Parity for webinocrm login flows: OTP (cache-backed) and password registration.
 */
class AuthParityController extends Controller
{
    public function sendLoginOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mobile' => 'required|string|min:10|max:20',
        ]);
        $code = (string) random_int(100000, 999999);
        Cache::put('otp_login:'.$data['mobile'], $code, now()->addMinutes(5));

        Log::info('auth.otp.login.generated', [
            'mobile' => $data['mobile'],
            'code' => $code,
        ]);

        $smsSettings = IntegrationSetting::getJson('sms', 'settings', []);
        $provider = $smsSettings['provider'] ?? config('integrations.sms.default', 'log');
        if ($provider !== 'disabled' && $provider !== 'stub') {
            try {
                app(SmsIntegrationController::class)->send(new Request([
                    'to' => $data['mobile'],
                    'message' => 'کد ورود شما: '.$code,
                ]));
            } catch (\Throwable $e) {
                Log::warning('auth.otp.sms.failed', ['error' => $e->getMessage()]);
            }
        } else {
            Log::channel('single')->info('sms.otp.login', [
                'provider' => $provider,
                'to' => $data['mobile'],
                'message' => 'کد ورود شما: '.$code,
            ]);
        }

        $payload = [
            'sent' => true,
            'message' => 'OTP sent',
        ];
        if (config('app.debug') && app()->environment('local')) {
            $payload['debug_code'] = $code;
        }

        return response()->json(['data' => $payload]);
    }

    public function verifyLoginOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mobile' => 'required|string|min:10|max:20',
            'code' => 'required|string|size:6',
        ]);
        $key = 'otp_login:'.$data['mobile'];
        $expected = Cache::get($key);
        if (! $expected || $expected !== $data['code']) {
            return response()->json([
                'data' => ['verified' => false, 'message' => 'Invalid code'],
            ], 422);
        }
        Cache::forget($key);

        $user = User::query()->where('phone', $data['mobile'])->first();
        if (! $user) {
            $user = User::query()->create([
                'name' => 'User '.$data['mobile'],
                'email' => 'u'.$data['mobile'].'@phone.local',
                'phone' => $data['mobile'],
                'password' => Hash::make(Str::random(32)),
            ]);
            $user->assignRole(RolesAndPermissionsSeeder::ROLE_CLIENT);
        }

        $tokenObj = $user->createToken('otp-login');
        $tokenObj->accessToken->forceFill([
            'device_name' => substr((string) $request->header('X-Device-Name', 'otp-login'), 0, 120),
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 2000),
            'last_activity_at' => now(),
        ])->save();
        $token = $tokenObj->plainTextToken;

        return response()->json([
            'data' => [
                'verified' => true,
                'token' => $token,
                'user' => $user->fresh(['roles']),
            ],
        ]);
    }

    public function setPassword(Request $request): JsonResponse
    {
        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);
        $user = $request->user();
        $user->update(['password' => Hash::make($request->input('password'))]);

        return response()->json(['data' => ['ok' => true]]);
    }

    /**
     * Parity with webinocrm `webino_auto_login` — replaced with a secure single-use signed token flow:
     *
     *   1. Admin (or server) calls `issueAutoLoginToken` to obtain a one-shot token bound to a user ID
     *      with 5-minute TTL stored in cache.
     *   2. Client posts `{token}` to `/auth/auto-login` which validates the HMAC signature + TTL,
     *      consumes the cache entry (single use), and returns a Sanctum access token.
     *
     * Rationale: webinocrm disabled the original public-nonce flow for security; this is the
     * functional replacement that keeps parity with the public endpoint surface.
     */
    public function autoLogin(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'token' => 'required|string|size:64',
            'uid' => 'required|integer|min:1',
            'exp' => 'required|integer',
            'sig' => 'required|string|size:64',
        ]);

        $expectedSig = hash_hmac(
            'sha256',
            $payload['token'].'|'.$payload['uid'].'|'.$payload['exp'],
            (string) config('app.key'),
        );
        if (! hash_equals($expectedSig, $payload['sig'])) {
            return response()->json([
                'data' => ['token' => null, 'message' => 'امضای نامعتبر'],
            ], 401);
        }
        if ($payload['exp'] < time()) {
            return response()->json([
                'data' => ['token' => null, 'message' => 'توکن منقضی شده'],
            ], 401);
        }

        $cacheKey = 'auto_login:'.$payload['token'];
        $stored = Cache::pull($cacheKey);
        if (! is_array($stored) || (int) ($stored['uid'] ?? 0) !== (int) $payload['uid']) {
            return response()->json([
                'data' => ['token' => null, 'message' => 'توکن مصرف‌شده یا نامعتبر'],
            ], 401);
        }

        $user = User::query()->find($payload['uid']);
        if (! $user || $user->is_active === false) {
            return response()->json([
                'data' => ['token' => null, 'message' => 'کاربر یافت نشد'],
            ], 404);
        }

        $tokenObj = $user->createToken('auto-login', ['*'], now()->addHours(12));
        $tokenObj->accessToken->forceFill([
            'device_name' => substr((string) $request->header('X-Device-Name', 'auto-login'), 0, 120),
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 2000),
            'last_activity_at' => now(),
        ])->save();
        $access = $tokenObj->plainTextToken;

        Log::info('auth.auto_login.consumed', [
            'uid' => $user->id,
            'reason' => $stored['reason'] ?? null,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'data' => [
                'token' => $access,
                'user' => $user->fresh(['roles']),
            ],
        ]);
    }

    /**
     * Admin-only endpoint to mint a one-shot auto-login token for another user.
     * Used by support / backoffice flows; endpoint is registered only with `auth:sanctum` + role gate.
     */
    public function issueAutoLoginToken(Request $request): JsonResponse
    {
        $actor = $request->user();
        if (! $actor || ! $actor->hasRole('system_manager')) {
            return response()->json([
                'data' => ['message' => 'دسترسی کافی ندارید'],
            ], 403);
        }

        $data = $request->validate([
            'user_id' => 'required|integer|min:1',
            'reason' => 'nullable|string|max:255',
        ]);
        $target = User::query()->findOrFail($data['user_id']);

        $token = bin2hex(random_bytes(32));
        $exp = now()->addMinutes(5)->timestamp;
        $sig = hash_hmac('sha256', $token.'|'.$target->id.'|'.$exp, (string) config('app.key'));

        Cache::put('auto_login:'.$token, [
            'uid' => $target->id,
            'issued_by' => $actor->id,
            'reason' => $data['reason'] ?? null,
        ], now()->addMinutes(5));

        Log::info('auth.auto_login.issued', [
            'target' => $target->id,
            'issuer' => $actor->id,
            'reason' => $data['reason'] ?? null,
        ]);

        return response()->json([
            'data' => [
                'token' => $token,
                'uid' => $target->id,
                'exp' => $exp,
                'sig' => $sig,
                'expires_in_seconds' => 300,
            ],
        ]);
    }

    public function sendEmailOtp(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => 'required|email']);
        $code = (string) random_int(100000, 999999);
        Cache::put('otp_email:'.$data['email'], $code, now()->addMinutes(10));

        return response()->json([
            'data' => [
                'sent' => true,
                'debug_code' => (config('app.debug') && app()->environment('local')) ? $code : null,
            ],
        ]);
    }

    public function verifyEmailOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
        ]);
        $key = 'otp_email:'.$data['email'];
        if (Cache::get($key) !== $data['code']) {
            return response()->json(['data' => ['verified' => false]], 422);
        }
        Cache::forget($key);

        return response()->json(['data' => ['verified' => true]]);
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:191',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);
        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);
        $user->assignRole(RolesAndPermissionsSeeder::ROLE_CLIENT);

        return response()->json([
            'data' => [
                'user_id' => $user->id,
                'token' => tap($user->createToken('register'), function ($tokenObj) use ($request) {
                    $tokenObj->accessToken->forceFill([
                        'device_name' => substr((string) $request->header('X-Device-Name', 'register'), 0, 120),
                        'ip' => $request->ip(),
                        'user_agent' => substr((string) $request->userAgent(), 0, 2000),
                        'last_activity_at' => now(),
                    ])->save();
                })->plainTextToken,
                'user' => $user,
            ],
        ], 201);
    }
}
