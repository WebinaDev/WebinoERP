<?php

namespace Modules\Crm\Policies;

use App\Models\User;
use Modules\Crm\Entities\CrmAccount;

class CrmAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('crm.accounts.view');
    }

    public function view(User $user, CrmAccount $account): bool
    {
        return $user->can('crm.accounts.view');
    }

    public function create(User $user): bool
    {
        return $user->can('crm.accounts.manage');
    }

    public function update(User $user, CrmAccount $account): bool
    {
        return $user->can('crm.accounts.manage');
    }

    public function delete(User $user, CrmAccount $account): bool
    {
        return $user->can('crm.accounts.manage');
    }
}
