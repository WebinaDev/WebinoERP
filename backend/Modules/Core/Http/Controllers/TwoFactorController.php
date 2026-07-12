<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Database\Seeders\RolesAndPermissionsSeeder;

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
        $expected = Cache::get($key);

        if (! $expected || $expected !== $data['code']) {
            return response()->json(['message' => 'Invalid verification code'], 422);
        }

        Cache::forget($key);
        Cache::put('2fa:verified:'.$user->id, true, now()->addHours(12));

        return response()->json(['data' => ['verified' => true]]);
    }

    public function send(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->hasRole(RolesAndPermissionsSeeder::ROLE_SYSTEM_MANAGER), 403);

        $code = (string) random_int(100000, 999999);
        Cache::put('otp_admin:'.$user->id, $code, now()->addMinutes(10));

        return response()->json([
            'data' => ['sent' => true],
            'message' => app()->environment('local') ? "Dev code: {$code}" : 'Verification code sent',
        ]);
    }
}
