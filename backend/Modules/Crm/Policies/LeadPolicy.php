<?php

namespace Modules\Crm\Policies;

use App\Models\User;
use Modules\Crm\Entities\CrmLead;

class LeadPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('crm.leads.view');
    }

    public function view(User $user, CrmLead $lead): bool
    {
        return $user->can('crm.leads.view');
    }

    public function create(User $user): bool
    {
        return $user->can('crm.leads.manage');
    }

    public function update(User $user, CrmLead $lead): bool
    {
        return $user->can('crm.leads.manage');
    }

    public function delete(User $user, CrmLead $lead): bool
    {
        return $user->can('crm.leads.manage');
    }
}
