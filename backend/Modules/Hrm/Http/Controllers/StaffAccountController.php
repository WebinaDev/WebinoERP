<?php

namespace Modules\Hrm\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Core\Database\Seeders\RolesAndPermissionsSeeder;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Support\HrmAccess;

class StaffAccountController extends Controller
{
    public function show(HrmEmployee $staff): JsonResponse
    {
        $this->authorizeManage();

        return response()->json(['data' => $this->accountData($staff)]);
    }

    /** Create a minimal employee login or link an existing account with the same email. */
    public function provision(Request $request, HrmEmployee $staff): JsonResponse
    {
        $this->authorizeManage();
        if (! $staff->email) {
            throw ValidationException::withMessages(['email' => ['ابتدا برای پرونده کارمند ایمیل ثبت کنید.']]);
        }
        if ($staff->user_id) {
            throw ValidationException::withMessages(['account' => ['این کارمند از قبل حساب متصل دارد.']]);
        }

        [$user, $temporaryPassword, $created] = DB::transaction(function () use ($request, $staff) {
            $user = User::query()->where('email', $staff->email)->lockForUpdate()->first();
            $created = false;
            $temporaryPassword = null;

            if ($user) {
                if (HrmEmployee::query()->where('user_id', $user->id)->where('id', '!=', $staff->id)->exists()) {
                    throw ValidationException::withMessages(['email' => ['این حساب به کارمند دیگری متصل است.']]);
                }
                if ($user->hasRole(RolesAndPermissionsSeeder::ROLE_CLIENT)) {
                    throw ValidationException::withMessages(['email' => ['حساب مشتری را نمی‌توان به حساب کارمندی تبدیل کرد.']]);
                }
                if (! $user->hasRole(RolesAndPermissionsSeeder::ROLE_TEAM_MEMBER)) {
                    $user->assignRole(RolesAndPermissionsSeeder::ROLE_TEAM_MEMBER);
                }
                if ($staff->status !== 'active' && $user->is_active) {
                    $user->forceFill(['is_active' => false])->save();
                    $user->tokens()->delete();
                }
            } else {
                $temporaryPassword = Str::password(18);
                $user = User::query()->create([
                    'name' => trim($staff->first_name.' '.$staff->last_name),
                    'email' => $staff->email,
                    'password' => Hash::make($temporaryPassword),
                    'email_verified_at' => now(),
                ]);
                $user->forceFill(['is_active' => $staff->status === 'active', 'must_change_password' => true])->save();
                $user->assignRole(RolesAndPermissionsSeeder::ROLE_TEAM_MEMBER);
                $created = true;
            }

            $staff->forceFill(['user_id' => $user->id])->save();

            return [$user, $temporaryPassword, $created];
        });

        Log::notice('HRM employee account provisioned', [
            'actor_id' => $request->user()?->id,
            'employee_id' => $staff->id,
            'user_id' => $user->id,
            'created' => $created,
        ]);

        return response()->json([
            'data' => [
                'account' => $this->accountData($staff->fresh()),
                'temporary_password' => $temporaryPassword,
            ],
            'created' => $created,
        ], $created ? 201 : 200);
    }

    /** Generate a one-time temporary password and revoke existing API tokens. */
    public function resetPassword(Request $request, HrmEmployee $staff): JsonResponse
    {
        $this->authorizeManage();
        $user = $staff->user;
        if (! $user) {
            throw ValidationException::withMessages(['account' => ['برای این کارمند هنوز حسابی متصل نیست.']]);
        }
        $temporaryPassword = Str::password(18);
        $user->forceFill(['password' => Hash::make($temporaryPassword), 'must_change_password' => true])->save();
        $user->tokens()->delete();

        Log::notice('HRM employee account password reset', [
            'actor_id' => $request->user()?->id,
            'employee_id' => $staff->id,
            'user_id' => $user->id,
        ]);

        return response()->json(['data' => [
            'account' => $this->accountData($staff->fresh()),
            'temporary_password' => $temporaryPassword,
        ]]);
    }

    /** Unlink the employee record while keeping the user account and its history. */
    public function unlink(Request $request, HrmEmployee $staff): JsonResponse
    {
        $this->authorizeManage();
        $userId = $staff->user_id;
        $staff->forceFill(['user_id' => null])->save();
        Log::notice('HRM employee account unlinked', [
            'actor_id' => $request->user()?->id,
            'employee_id' => $staff->id,
            'user_id' => $userId,
        ]);

        return response()->json(['data' => $this->accountData($staff->fresh())]);
    }

    private function authorizeManage(): void
    {
        abort_unless(HrmAccess::canManageStaff(), 403);
    }

    /** @return array<string, mixed> */
    private function accountData(HrmEmployee $staff): array
    {
        $user = $staff->user;

        return [
            'employee_id' => $staff->id,
            'linked' => $user !== null,
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => (bool) $user->is_active,
                'roles' => $user->getRoleNames()->values(),
            ] : null,
        ];
    }
}
