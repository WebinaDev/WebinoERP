<?php

namespace Modules\Core\Services;

use App\Models\User;
use Modules\Core\Database\Seeders\RolesAndPermissionsSeeder;

/**
 * Role matrix for home dashboard widgets, KPI sections, and list feeds.
 */
class DashboardAccess
{
    public const ROLE_SYSTEM_MANAGER = RolesAndPermissionsSeeder::ROLE_SYSTEM_MANAGER;

    public const ROLE_FINANCE_MANAGER = RolesAndPermissionsSeeder::ROLE_FINANCE_MANAGER;

    public const ROLE_HR_MANAGER = RolesAndPermissionsSeeder::ROLE_HR_MANAGER;

    public const ROLE_PROJECT_MANAGER = RolesAndPermissionsSeeder::ROLE_PROJECT_MANAGER;

    public const ROLE_CRM_SPECIALIST = RolesAndPermissionsSeeder::ROLE_CRM_SPECIALIST;

    public const ROLE_SALES_CONSULTANT = RolesAndPermissionsSeeder::ROLE_SALES_CONSULTANT;

    public const ROLE_TEAM_MEMBER = RolesAndPermissionsSeeder::ROLE_TEAM_MEMBER;

    public const ROLE_CLIENT = RolesAndPermissionsSeeder::ROLE_CLIENT;

    /** @var array<string, list<string>> suite metric widgets → roles */
    public const SUITE_WIDGET_ROLES = [
        'forecast' => [
            self::ROLE_SYSTEM_MANAGER,
            self::ROLE_FINANCE_MANAGER,
            self::ROLE_CRM_SPECIALIST,
            self::ROLE_SALES_CONSULTANT,
        ],
        'reminders' => [
            self::ROLE_SYSTEM_MANAGER,
            self::ROLE_CRM_SPECIALIST,
            self::ROLE_SALES_CONSULTANT,
            self::ROLE_PROJECT_MANAGER,
            self::ROLE_TEAM_MEMBER,
        ],
        'win_loss' => [
            self::ROLE_SYSTEM_MANAGER,
            self::ROLE_FINANCE_MANAGER,
            self::ROLE_CRM_SPECIALIST,
            self::ROLE_SALES_CONSULTANT,
        ],
        'delays' => [
            self::ROLE_SYSTEM_MANAGER,
            self::ROLE_PROJECT_MANAGER,
            self::ROLE_TEAM_MEMBER,
            self::ROLE_CRM_SPECIALIST,
        ],
        'sla' => [
            self::ROLE_SYSTEM_MANAGER,
            self::ROLE_PROJECT_MANAGER,
            self::ROLE_CRM_SPECIALIST,
            self::ROLE_TEAM_MEMBER,
        ],
        'workload' => [
            self::ROLE_SYSTEM_MANAGER,
            self::ROLE_PROJECT_MANAGER,
            self::ROLE_HR_MANAGER,
            self::ROLE_TEAM_MEMBER,
        ],
    ];

    /** @var array<string, list<string>> list feed widgets → roles */
    public const LIST_WIDGET_ROLES = [
        'recent_leads' => [
            self::ROLE_SYSTEM_MANAGER,
            self::ROLE_CRM_SPECIALIST,
            self::ROLE_SALES_CONSULTANT,
        ],
        'recent_deals' => [
            self::ROLE_SYSTEM_MANAGER,
            self::ROLE_FINANCE_MANAGER,
            self::ROLE_CRM_SPECIALIST,
            self::ROLE_SALES_CONSULTANT,
        ],
        'recent_tasks' => [
            self::ROLE_SYSTEM_MANAGER,
            self::ROLE_PROJECT_MANAGER,
            self::ROLE_TEAM_MEMBER,
            self::ROLE_CRM_SPECIALIST,
            self::ROLE_HR_MANAGER,
        ],
        'recent_tickets' => [
            self::ROLE_SYSTEM_MANAGER,
            self::ROLE_PROJECT_MANAGER,
            self::ROLE_CRM_SPECIALIST,
            self::ROLE_TEAM_MEMBER,
            self::ROLE_CLIENT,
            self::ROLE_SALES_CONSULTANT,
        ],
        'recent_projects' => [
            self::ROLE_SYSTEM_MANAGER,
            self::ROLE_PROJECT_MANAGER,
            self::ROLE_TEAM_MEMBER,
            self::ROLE_CLIENT,
            self::ROLE_FINANCE_MANAGER,
        ],
        'recent_invoices' => [
            self::ROLE_SYSTEM_MANAGER,
            self::ROLE_FINANCE_MANAGER,
            self::ROLE_SALES_CONSULTANT,
        ],
        'recent_sites' => [
            self::ROLE_SYSTEM_MANAGER,
            self::ROLE_PROJECT_MANAGER,
        ],
    ];

    /** @var array<string, list<string>> CrmPmSnapshot section keys → roles */
    public const SECTION_ROLES = [
        'crm_kpis' => [
            self::ROLE_SYSTEM_MANAGER,
            self::ROLE_CRM_SPECIALIST,
            self::ROLE_SALES_CONSULTANT,
            self::ROLE_FINANCE_MANAGER,
        ],
        'pm_kpis' => [
            self::ROLE_SYSTEM_MANAGER,
            self::ROLE_PROJECT_MANAGER,
            self::ROLE_TEAM_MEMBER,
            self::ROLE_CRM_SPECIALIST,
        ],
        'funnel' => [
            self::ROLE_SYSTEM_MANAGER,
            self::ROLE_CRM_SPECIALIST,
            self::ROLE_SALES_CONSULTANT,
            self::ROLE_FINANCE_MANAGER,
        ],
        'trend' => [
            self::ROLE_SYSTEM_MANAGER,
            self::ROLE_FINANCE_MANAGER,
            self::ROLE_CRM_SPECIALIST,
            self::ROLE_PROJECT_MANAGER,
            self::ROLE_SALES_CONSULTANT,
        ],
        'finance' => [
            self::ROLE_SYSTEM_MANAGER,
            self::ROLE_FINANCE_MANAGER,
        ],
        'tickets' => [
            self::ROLE_SYSTEM_MANAGER,
            self::ROLE_PROJECT_MANAGER,
            self::ROLE_CRM_SPECIALIST,
            self::ROLE_TEAM_MEMBER,
            self::ROLE_SALES_CONSULTANT,
        ],
        'sites' => [
            self::ROLE_SYSTEM_MANAGER,
            self::ROLE_PROJECT_MANAGER,
        ],
        'marketing' => [
            self::ROLE_SYSTEM_MANAGER,
            self::ROLE_CRM_SPECIALIST,
            self::ROLE_SALES_CONSULTANT,
        ],
        'suite' => [
            self::ROLE_SYSTEM_MANAGER,
            self::ROLE_FINANCE_MANAGER,
            self::ROLE_CRM_SPECIALIST,
            self::ROLE_SALES_CONSULTANT,
            self::ROLE_PROJECT_MANAGER,
            self::ROLE_TEAM_MEMBER,
            self::ROLE_HR_MANAGER,
        ],
        'todos' => [
            self::ROLE_SYSTEM_MANAGER,
            self::ROLE_PROJECT_MANAGER,
            self::ROLE_TEAM_MEMBER,
            self::ROLE_CRM_SPECIALIST,
            self::ROLE_SALES_CONSULTANT,
            self::ROLE_HR_MANAGER,
        ],
        'notifications' => [
            self::ROLE_SYSTEM_MANAGER,
            self::ROLE_FINANCE_MANAGER,
            self::ROLE_HR_MANAGER,
            self::ROLE_PROJECT_MANAGER,
            self::ROLE_CRM_SPECIALIST,
            self::ROLE_SALES_CONSULTANT,
            self::ROLE_TEAM_MEMBER,
        ],
        'team_tab' => [
            self::ROLE_SYSTEM_MANAGER,
            self::ROLE_PROJECT_MANAGER,
            self::ROLE_HR_MANAGER,
            self::ROLE_FINANCE_MANAGER,
            self::ROLE_CRM_SPECIALIST,
        ],
        'full_overview' => [
            self::ROLE_SYSTEM_MANAGER,
            self::ROLE_FINANCE_MANAGER,
            self::ROLE_CRM_SPECIALIST,
            self::ROLE_PROJECT_MANAGER,
            self::ROLE_SALES_CONSULTANT,
            self::ROLE_HR_MANAGER,
        ],
        'widget_settings' => [
            self::ROLE_SYSTEM_MANAGER,
            self::ROLE_FINANCE_MANAGER,
            self::ROLE_CRM_SPECIALIST,
            self::ROLE_PROJECT_MANAGER,
            self::ROLE_SALES_CONSULTANT,
            self::ROLE_HR_MANAGER,
            self::ROLE_TEAM_MEMBER,
        ],
    ];

    public function __construct(private readonly DashboardNavigationService $navigation) {}

    public function roleFor(?User $user): string
    {
        if (! $user) {
            return 'guest';
        }

        return $this->navigation->resolveDashboardRole($user);
    }

    public function canSuiteWidget(string $role, string $key): bool
    {
        return $this->roleAllowed($role, self::SUITE_WIDGET_ROLES[$key] ?? []);
    }

    public function canListWidget(string $role, string $key): bool
    {
        return $this->roleAllowed($role, self::LIST_WIDGET_ROLES[$key] ?? []);
    }

    public function canSection(string $role, string $key): bool
    {
        return $this->roleAllowed($role, self::SECTION_ROLES[$key] ?? []);
    }

    /**
     * @return list<string>
     */
    public function allowedSuiteKeys(string $role): array
    {
        return array_values(array_filter(
            array_keys(self::SUITE_WIDGET_ROLES),
            fn (string $key) => $this->canSuiteWidget($role, $key),
        ));
    }

    /**
     * @return list<string>
     */
    public function allowedListKeys(string $role): array
    {
        return array_values(array_filter(
            array_keys(self::LIST_WIDGET_ROLES),
            fn (string $key) => $this->canListWidget($role, $key),
        ));
    }

    /**
     * @return list<string>
     */
    public function allowedSections(string $role): array
    {
        return array_values(array_filter(
            array_keys(self::SECTION_ROLES),
            fn (string $key) => $this->canSection($role, $key),
        ));
    }

    /**
     * @param  list<string>  $roles
     */
    private function roleAllowed(string $role, array $roles): bool
    {
        if ($role === self::ROLE_SYSTEM_MANAGER) {
            return true;
        }

        return in_array($role, $roles, true);
    }
}
