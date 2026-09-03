<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Database\Seeders\RolesAndPermissionsSeeder;
use Symfony\Component\HttpFoundation\Response;

/**
 * system_manager must complete 2FA within the current window before accessing
 * privileged API routes. Auth + 2FA endpoints themselves are exempt.
 */
class RequireTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole(RolesAndPermissionsSeeder::ROLE_SYSTEM_MANAGER)) {
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
        ];
        if (in_array($path, $exempt, true)) {
            return $next($request);
        }

        if (! Cache::get('2fa:verified:'.$user->id, false)) {
            return response()->json([
                'message' => 'Two-factor authentication required',
                'errors' => ['code' => '2FA_REQUIRED'],
            ], 403);
        }

        return $next($request);
    }
}
