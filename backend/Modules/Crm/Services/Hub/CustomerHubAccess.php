<?php

namespace Modules\Crm\Services\Hub;

use App\Models\User;

/**
 * Role-based access per hub section. A user sees only the sections whose
 * permissions they hold (finance → billing, support → tickets, ...).
 * system_manager sees everything.
 */
class CustomerHubAccess
{
    /** section => [view permissions (any of)] */
    public const VIEW = [
        'identity' => ['crm.accounts.view'],
        'assets' => ['crm.accounts.view', 'platform.view', 'site_builder.provision.view'],
        'licenses' => ['core.licenses.view', 'crm.accounts.view'],
        'billing' => ['sales.invoices.view', 'accounting.view', 'projects.invoices.view'],
        'sales' => ['crm.deals.view', 'projects.projects.view'],
        'support' => ['projects.tickets.view', 'crm.tickets.view'],
        'usage' => ['crm.accounts.view', 'platform.view'],
        'site_panel' => ['crm.accounts.view', 'projects.tickets.view', 'sales.invoices.view'],
        'documents' => ['crm.accounts.view', 'accounting.view'],
        'access' => ['core.users.view', 'crm.accounts.manage'],
        'relations' => ['crm.accounts.view'],
        'notes' => ['crm.accounts.view', 'projects.tickets.view', 'sales.invoices.view'],
        'analytics' => ['crm.accounts.view', 'accounting.reports.view'],
        'timeline' => ['crm.accounts.view', 'crm.activities.view', 'projects.tickets.view', 'sales.invoices.view'],
    ];

    /** action => [manage permissions (any of)] */
    public const MANAGE = [
        'profile' => ['crm.accounts.manage'],
        'contacts' => ['crm.accounts.manage', 'crm.contacts.manage'],
        'documents' => ['crm.accounts.manage'],
        'assets' => ['crm.accounts.manage', 'platform.manage'],
        'relations' => ['crm.accounts.manage'],
        'notes' => ['crm.accounts.manage', 'crm.activities.manage', 'projects.tickets.manage', 'sales.invoices.manage'],
        'invoice' => ['sales.invoices.manage'],
        'payment' => ['sales.invoices.manage', 'accounting.receipt.manage'],
        'license' => ['core.licenses.manage'],
        'ticket' => ['projects.tickets.manage', 'crm.tickets.manage'],
        'project' => ['projects.projects.manage'],
        'owner' => ['crm.accounts.manage'],
        'suspend' => ['crm.accounts.manage'],
    ];

    public function isAdmin(User $user): bool
    {
        try {
            return $user->hasRole('system_manager');
        } catch (\Throwable) {
            return false;
        }
    }

    /** Portal customers (client role) never get the internal hub, even with ticket permissions. */
    public function isPortalCustomer(User $user): bool
    {
        try {
            return ! $this->isAdmin($user) && $user->hasRole('client');
        } catch (\Throwable) {
            return false;
        }
    }

    private function any(User $user, array $perms): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }
        if ($this->isPortalCustomer($user)) {
            return false;
        }
        foreach ($perms as $p) {
            try {
                if ($user->hasPermissionTo($p)) {
                    return true;
                }
            } catch (\Throwable) {
                // permission not in catalog
            }
        }

        return false;
    }

    /** @return list<string> */
    public function sections(User $user): array
    {
        if ($this->isPortalCustomer($user)) {
            return [];
        }
        return array_values(array_filter(array_keys(self::VIEW), fn ($s) => $this->any($user, self::VIEW[$s])));
    }

    public function canView(User $user, string $section): bool
    {
        return isset(self::VIEW[$section]) && $this->any($user, self::VIEW[$section]);
    }

    public function canManage(User $user, string $action): bool
    {
        return isset(self::MANAGE[$action]) && $this->any($user, self::MANAGE[$action]);
    }

    /** @return array<string,bool> */
    public function actions(User $user): array
    {
        $out = [];
        foreach (array_keys(self::MANAGE) as $a) {
            $out[$a] = $this->canManage($user, $a);
        }

        return $out;
    }
}
