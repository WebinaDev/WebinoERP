<?php

namespace Modules\Integrations\Policies;

use App\Models\User;

class ModirPayamakPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('integrations.modirpayamak.view');
    }

    public function manage(User $user): bool
    {
        return $user->can('integrations.modirpayamak.manage');
    }

    public function admin(User $user): bool
    {
        return $user->hasRole('system_manager') || $user->can('integrations.modirpayamak.manage');
    }
}
