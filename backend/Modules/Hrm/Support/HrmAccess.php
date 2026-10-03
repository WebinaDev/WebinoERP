<?php

namespace Modules\Hrm\Support;

use Modules\Hrm\Entities\HrmEmployee;

class HrmAccess
{
    public static function employee(?int $userId = null): ?HrmEmployee
    {
        $userId ??= auth()->id();
        if (! $userId) {
            return null;
        }

        return HrmEmployee::query()->where('user_id', $userId)->first();
    }

    public static function seesAllStaff(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }
        try {
            if (method_exists($user, 'hasRole') && ($user->hasRole('hr_manager') || $user->hasRole('system_manager'))) {
                return true;
            }

            return (bool) $user->can('hrm.staff.view');
        } catch (\Throwable) {
            return false;
        }
    }

    public static function canManageStaff(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }
        try {
            if (method_exists($user, 'hasRole') && ($user->hasRole('hr_manager') || $user->hasRole('system_manager'))) {
                return true;
            }

            return (bool) $user->can('hrm.staff.manage');
        } catch (\Throwable) {
            return false;
        }
    }
}
