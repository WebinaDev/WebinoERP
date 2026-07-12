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
    /**
     * Handle login request
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (!$user->is_active) {
            return response()->json([
                'error' => [
                    'code' => 'ACCOUNT_DISABLED',
                    'message' => 'Your account has been disabled.',
                ],
            ], 403);
        }

        // Update last login
        $user->update(['last_login_at' => now()]);

        // Create token for API or session for web
        if ($request->wantsJson()) {
            $tokenObj = $user->createToken('api-token');
            $tokenObj->accessToken->forceFill([
                'device_name' => substr((string) $request->header('X-Device-Name', 'web'), 0, 120),
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 2000),
                'last_activity_at' => now(),
            ])->save();
            $token = $tokenObj->plainTextToken;
            
            return response()->json([
                'data' => [
                    'user' => $user,
                    'token' => $token,
                ],
            ]);
        }

        // Web session-based authentication
        Auth::login($user, $request->boolean('remember'));

        return response()->json([
            'data' => [
                'user' => $user,
            ],
        ]);
    }

    /**
     * Handle logout request
     */
    public function logout(Request $request): JsonResponse
    {
        if ($request->user()) {
            $request->user()->tokens()->delete();
        }

        Auth::logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Get authenticated user
     */
    public function user(Request $request, DashboardNavigationService $navigation): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Not authenticated',
                ],
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

    /**
     * Get active modules for user
     */
    private function getLicensedModules(): array
    {
        $modules = \Modules\Core\Entities\SystemModule::where('is_active', true)
            ->pluck('slug')
            ->toArray();

        return $modules;
    }
}

