<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Support\AdminTwoFactor;
use Symfony\Component\HttpFoundation\Response;

/**
 * When admin 2FA is actually configured, system_manager must finish the
 * challenge (full-ability token or verified cache) before privileged APIs.
 */
class RequireTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! AdminTwoFactor::requiredFor($user)) {
            return $next($request);
        }

        $path = trim($request->path(), '/');
        $exempt = [
            'api/v1/core/auth/2fa/status',
            'api/v1/core/auth/2fa/send',
            'api/v1/core/auth/2fa/verify',
            'api/v1/core/auth/logout',
            'api/v1/core/auth/user',
            'api/v1/core/auth/gate',
            'api/v1/core/auth/refresh',
            'api/v1/core/auth/login',
            'api/v1/core/auth/otp/send',
            'api/v1/core/auth/otp/verify',
            'api/v1/core/auth/register',
        ];
        if (in_array($path, $exempt, true)) {
            return $next($request);
        }

        $token = $user->currentAccessToken();
        if ($token && method_exists($token, 'can') && $token->can('*')) {
            return $next($request);
        }

        if (Cache::get('2fa:verified:'.$user->id, false)) {
            return $next($request);
        }

        return response()->json([
            'message' => 'احراز هویت دو مرحله‌ای لازم است.',
            'errors' => ['code' => '2FA_REQUIRED'],
        ], 403);
    }
}
