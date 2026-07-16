<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use Modules\Core\Services\DashboardNavigationService;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('api.unauthorized')],
            ]);
        }

        if (! $user->is_active) {
            return response()->json([
                'message' => __('api.forbidden'),
                'errors' => ['code' => 'ACCOUNT_DISABLED'],
            ], 403);
        }

        $user->update(['last_login_at' => now()]);

        $tokenObj = $user->createToken('spa');
        $tokenObj->accessToken->forceFill([
            'device_name' => substr((string) $request->header('X-Device-Name', 'web'), 0, 120),
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 2000),
            'last_activity_at' => now(),
        ])->save();
        $token = $tokenObj->plainTextToken;

        $response = response()->json([
            'data' => [
                'user' => $user,
            ],
        ]);

        return $this->attachAuthCookie($response, $token);
    }

    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => __('api.unauthorized')], 401);
        }

        $request->user()?->currentAccessToken()?->delete();

        $tokenObj = $user->createToken('spa');
        $token = $tokenObj->plainTextToken;

        $response = response()->json([
            'data' => [
                'user' => $user,
                'refreshed' => true,
            ],
        ]);

        return $this->attachAuthCookie($response, $token);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        Auth::logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return $this->clearAuthCookie(response()->json([
            'message' => __('api.logged_out'),
        ]));
    }

    public function user(Request $request, DashboardNavigationService $navigation): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => __('api.unauthorized'),
                'errors' => ['code' => 'UNAUTHORIZED'],
            ], 401);
        }

        return response()->json([
            'data' => [
                'user' => $user->load('roles', 'permissions'),
                'dashboard_role' => $navigation->resolveDashboardRole($user),
                'licensed_modules' => $this->getLicensedModules(),
                'active_modules' => $this->getLicensedModules(),
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
            ],
        ]);
    }

    private function getLicensedModules(): array
    {
        return \Modules\Core\Entities\SystemModule::where('is_active', true)
            ->pluck('slug')
            ->toArray();
    }

    private function attachAuthCookie(JsonResponse $response, string $token): JsonResponse
    {
        return $response->cookie(
            config('auth.cookie_name', 'webino_auth_token'),
            $token,
            config('auth.cookie_max_minutes', 60 * 24 * 7),
            '/',
            null,
            app()->environment('production'),
            true,
            false,
            'lax'
        );
    }

    private function clearAuthCookie(JsonResponse $response): JsonResponse
    {
        return $response->cookie(
            config('auth.cookie_name', 'webino_auth_token'),
            '',
            -1,
            '/',
            null,
            app()->environment('production'),
            true,
            false,
            'lax'
        );
    }
}
