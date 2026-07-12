<?php

namespace Modules\Hrm\Policies;

use App\Models\User;
use Modules\Hrm\Entities\HrmEmployee;

class HrmEmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hrm.staff.view');
    }

    public function view(User $user, HrmEmployee $employee): bool
    {
        return $user->can('hrm.staff.view');
    }

    public function create(User $user): bool
    {
        return $user->can('hrm.staff.manage');
    }

    public function update(User $user, HrmEmployee $employee): bool
    {
        return $user->can('hrm.staff.manage');
    }

    public function delete(User $user, HrmEmployee $employee): bool
    {
        return $user->can('hrm.staff.manage');
    }
}
