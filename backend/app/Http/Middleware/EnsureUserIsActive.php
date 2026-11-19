<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && $user->is_active === false) {
            $user->currentAccessToken()?->delete();

            return response()->json([
                'message' => __('api.forbidden'),
                'errors' => ['code' => 'ACCOUNT_DISABLED'],
            ], 403);
        }

        if ($user && method_exists($user, 'isLocked') && $user->isLocked()) {
            $user->currentAccessToken()?->delete();

            return response()->json([
                'message' => __('api.forbidden'),
                'errors' => ['code' => 'ACCOUNT_LOCKED'],
            ], 403);
        }

        // A temporary password must be replaced before the account can change anything else.
        if ($user && ($user->must_change_password ?? false) === true
            && ! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)
            && ! $this->allowedDuringPasswordChange($request->path())) {
            return response()->json([
                'message' => 'ابتدا رمز عبور موقت خود را تغییر دهید.',
                'errors' => ['code' => 'PASSWORD_CHANGE_REQUIRED'],
            ], 403);
        }

        return $next($request);
    }

    private function allowedDuringPasswordChange(string $path): bool
    {
        foreach ([
            'api/v1/core/me/password',
            'api/v1/core/auth/',
            'api/v1/core/users/me',
            'api/v1/core/visitor-stats',
            'api/v1/core/logs/user-actions',
        ] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
