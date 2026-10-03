<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Database\Seeders\RolesAndPermissionsSeeder;

/**
 * Portal customers only see CRM/PM rows tied to their account or user.
 * Staff roles keep the full module scope.
 */
class CustomerAccess
{
    /** @var list<string> */
    public const STAFF_ROLES = [
        RolesAndPermissionsSeeder::ROLE_SYSTEM_MANAGER,
        RolesAndPermissionsSeeder::ROLE_FINANCE_MANAGER,
        RolesAndPermissionsSeeder::ROLE_PROJECT_MANAGER,
        RolesAndPermissionsSeeder::ROLE_CRM_SPECIALIST,
        RolesAndPermissionsSeeder::ROLE_TEAM_MEMBER,
        RolesAndPermissionsSeeder::ROLE_SALES_CONSULTANT,
    ];

    public function isPortalCustomer(?User $user): bool
    {
        if (! $user || ! $user->hasRole(RolesAndPermissionsSeeder::ROLE_CLIENT)) {
            return false;
        }

        foreach (self::STAFF_ROLES as $role) {
            if ($user->hasRole($role)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<int>
     */
    public function accountIds(User $user): array
    {
        return $user->crmAccounts()->pluck('crm_accounts.id')->map(fn ($id) => (int) $id)->all();
    }

    public function scopeProjects(Builder $query, User $user): Builder
    {
        if (! $this->isPortalCustomer($user)) {
            return $query;
        }

        return $query->whereIn('customer_account_id', $this->accountIds($user));
    }

    public function scopeTickets(Builder $query, User $user): Builder
    {
        if (! $this->isPortalCustomer($user)) {
            return $query;
        }

        $ids = $this->accountIds($user);

        return $query->where(function (Builder $w) use ($user, $ids) {
            $w->where('customer_user_id', $user->id);
            if ($ids !== []) {
                $w->orWhereIn('customer_account_id', $ids);
            }
        });
    }

    public function scopeAppointments(Builder $query, User $user): Builder
    {
        if (! $this->isPortalCustomer($user)) {
            return $query;
        }

        $ids = $this->accountIds($user);

        return $query->where(function (Builder $w) use ($user, $ids) {
            $w->where('customer_user_id', $user->id);
            if ($ids !== []) {
                $w->orWhereIn('customer_account_id', $ids);
            }
        });
    }

    public function scopeContracts(Builder $query, User $user): Builder
    {
        if (! $this->isPortalCustomer($user)) {
            return $query;
        }

        return $query->whereIn('customer_account_id', $this->accountIds($user));
    }

    public function scopeInvoices(Builder $query, User $user): Builder
    {
        if (! $this->isPortalCustomer($user)) {
            return $query;
        }

        $ids = $this->accountIds($user);

        return $query->where(function (Builder $w) use ($user, $ids) {
            $w->where('customer_user_id', $user->id);
            if ($ids !== []) {
                $w->orWhereIn('project_id', function ($sub) use ($ids) {
                    $sub->select('id')->from('prj_projects')->whereIn('customer_account_id', $ids);
                });
            }
        });
    }

    public function scopeTasks(Builder $query, User $user): Builder
    {
        if (! $this->isPortalCustomer($user)) {
            return $query;
        }

        $ids = $this->accountIds($user);

        return $query->whereIn('project_id', function ($sub) use ($ids) {
            $sub->select('id')->from('prj_projects')->whereIn('customer_account_id', $ids === [] ? [-1] : $ids);
        });
    }
}
