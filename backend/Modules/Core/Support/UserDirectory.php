<?php

namespace Modules\Core\Support;

use App\Models\User;
use App\Support\CustomerAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Database\Seeders\RolesAndPermissionsSeeder;

/** Shapes users for the management screens and classifies them as staff or customer. */
final class UserDirectory
{
    /** @return list<string> */
    public static function staffRoles(): array
    {
        return array_values(array_unique(array_merge(CustomerAccess::STAFF_ROLES, [RolesAndPermissionsSeeder::ROLE_HR_MANAGER])));
    }

    public static function applyType(Builder $q, string $type): void
    {
        $hasEmployees = Schema::hasTable('hrm_employees');
        $hasAccounts = Schema::hasTable('crm_account_users');
        if ($type === 'staff') {
            $q->where(function (Builder $w) use ($hasEmployees) {
                $w->whereHas('roles', fn ($r) => $r->whereIn('name', self::staffRoles()));
                if ($hasEmployees) {
                    $w->orWhereIn('users.id', DB::table('hrm_employees')->whereNotNull('user_id')->whereNull('deleted_at')->select('user_id'));
                }
            });
        } elseif ($type === 'customer') {
            $q->whereDoesntHave('roles', fn ($r) => $r->whereIn('name', self::staffRoles()))
                ->where(function (Builder $w) use ($hasAccounts) {
                    $w->whereHas('roles', fn ($r) => $r->where('name', RolesAndPermissionsSeeder::ROLE_CLIENT));
                    if ($hasAccounts) {
                        $w->orWhereIn('users.id', DB::table('crm_account_users')->select('user_id'));
                    }
                });
        } elseif ($type === 'other') {
            $q->whereDoesntHave('roles');
        }
    }

    public static function typeOf(User $user, ?array $roles = null, bool $isEmployee = false): string
    {
        $roles ??= $user->getRoleNames()->all();
        if ($isEmployee || array_intersect($roles, self::staffRoles()) !== []) {
            return 'staff';
        }
        if (in_array(RolesAndPermissionsSeeder::ROLE_CLIENT, $roles, true)) {
            return 'customer';
        }

        return 'other';
    }

    /** @return array<string, mixed> */
    public static function row(User $user, ?int $employeeId = null): array
    {
        $roles = $user->roles->pluck('name')->values()->all();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'username' => $user->username ?? null,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar_url' => $user->avatar_path ? Storage::disk('public')->url($user->avatar_path) : null,
            'is_active' => $user->is_active !== false,
            'locked' => $user->isLocked(),
            'locked_until' => $user->locked_until?->toIso8601String(),
            'must_change_password' => (bool) ($user->must_change_password ?? false),
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
            'roles' => $roles,
            'type' => self::typeOf($user, $roles, $employeeId !== null),
            'employee_id' => $employeeId,
        ];
    }

    /**
     * @param  iterable<User>  $users
     * @return array<int, int>
     */
    public static function employeeIds(iterable $users): array
    {
        if (! Schema::hasTable('hrm_employees')) {
            return [];
        }
        $ids = [];
        foreach ($users as $u) {
            $ids[] = $u->id;
        }
        if ($ids === []) {
            return [];
        }

        return DB::table('hrm_employees')->whereIn('user_id', $ids)->whereNull('deleted_at')
            ->pluck('id', 'user_id')->map(fn ($v) => (int) $v)->all();
    }
}
