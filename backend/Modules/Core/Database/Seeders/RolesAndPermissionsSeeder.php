<?php

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * WebinoCRM parity: roles align with WordPress dashboard (see webinocrm sidebar + router).
 * Guard: web (session) — Sanctum uses same User model; permissions checked on API with auth:sanctum.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public const ROLE_SYSTEM_MANAGER = 'system_manager';

    public const ROLE_FINANCE_MANAGER = 'finance_manager';

    public const ROLE_TEAM_MEMBER = 'team_member';

    public const ROLE_SALES_CONSULTANT = 'sales_consultant';

    public const ROLE_CRM_SPECIALIST = 'crm_specialist';

    public const ROLE_PROJECT_MANAGER = 'project_manager';

    public const ROLE_CLIENT = 'client';

    public const ROLE_HR_MANAGER = 'hr_manager';

    public const LABELS = [
        self::ROLE_SYSTEM_MANAGER => 'مدیر سیستم',
        self::ROLE_FINANCE_MANAGER => 'مدیر مالی',
        self::ROLE_TEAM_MEMBER => 'عضو تیم',
        self::ROLE_SALES_CONSULTANT => 'کارشناس فروش',
        self::ROLE_CRM_SPECIALIST => 'کارشناس ارتباط با مشتری',
        self::ROLE_PROJECT_MANAGER => 'مدیر پروژه',
        self::ROLE_CLIENT => 'مشتری',
        self::ROLE_HR_MANAGER => 'مدیر منابع انسانی',
    ];

    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $guard = 'web';

        foreach ($this->permissionDefinitions() as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => $guard]);
        }

        $all = Permission::all();

        Role::firstOrCreate(['name' => self::ROLE_SYSTEM_MANAGER, 'guard_name' => $guard])
            ->syncPermissions($all);

        Role::firstOrCreate(['name' => self::ROLE_FINANCE_MANAGER, 'guard_name' => $guard])
            ->syncPermissions(Permission::whereIn('name', $this->financeManagerPermissions())->get());

        Role::firstOrCreate(['name' => self::ROLE_TEAM_MEMBER, 'guard_name' => $guard])
            ->syncPermissions(Permission::whereIn('name', $this->teamMemberPermissions())->get());

        Role::firstOrCreate(['name' => self::ROLE_SALES_CONSULTANT, 'guard_name' => $guard])
            ->syncPermissions(Permission::whereIn('name', $this->salesConsultantPermissions())->get());

        Role::firstOrCreate(['name' => self::ROLE_CRM_SPECIALIST, 'guard_name' => $guard])
            ->syncPermissions(Permission::whereIn('name', $this->crmSpecialistPermissions())->get());

        Role::firstOrCreate(['name' => self::ROLE_PROJECT_MANAGER, 'guard_name' => $guard])
            ->syncPermissions(Permission::whereIn('name', $this->projectManagerPermissions())->get());

        Role::firstOrCreate(['name' => self::ROLE_CLIENT, 'guard_name' => $guard])
            ->syncPermissions(Permission::whereIn('name', $this->clientPermissions())->get());

        Role::firstOrCreate(['name' => self::ROLE_HR_MANAGER, 'guard_name' => $guard])
            ->syncPermissions(Permission::whereIn('name', $this->hrManagerPermissions())->get());
    }

    /**
     * @return list<string>
     */
    private function permissionDefinitions(): array
    {
        return array_merge(
            [
                'core.navigation.view',
                'core.settings.view',
                'core.settings.manage',
                'core.users.view',
                'core.users.manage',
                'core.modules.view',
                'core.modules.manage',
                'core.dashboard.view',
                'core.dashboard.reports',
                'core.logs.view',
                'core.logs.manage',
                'core.visitor_stats.view',
                'core.licenses.view',
                'core.licenses.manage',
                'core.notifications.view',
            ],
            [
                'crm.leads.view',
                'crm.leads.manage',
                'crm.accounts.view',
                'crm.accounts.manage',
                'crm.contacts.view',
                'crm.contacts.manage',
                'crm.deals.view',
                'crm.deals.manage',
                'crm.consultations.view',
                'crm.consultations.manage',
                'crm.campaigns.view',
                'crm.campaigns.manage',
                'crm.services.view',
                'crm.services.manage',
                'crm.staff.view',
                'crm.staff.manage',
                'crm.tickets.view',
                'crm.tickets.manage',
                'crm.bale_business.view',
                'crm.bale_business.manage',
                'crm.activities.view',
                'crm.activities.manage',
                'crm.messages.view',
                'crm.messages.manage',
                'crm.forecast.view',
                'crm.audiences.view',
                'crm.audiences.manage',
                'crm.audit.view',
            ],
            [
                'projects.projects.view',
                'projects.projects.manage',
                'projects.contracts.view',
                'projects.contracts.manage',
                'projects.tasks.view',
                'projects.tasks.manage',
                'projects.tickets.view',
                'projects.tickets.manage',
                'projects.appointments.view',
                'projects.appointments.manage',
                'projects.invoices.view',
                'projects.invoices.manage',
                'projects.import_export',
                'projects.workload.view',
                'projects.files.view',
                'projects.files.manage',
                'projects.audit.view',
                'portal.access',
            ],
            [
                'accounting.view',
                'accounting.manage',
                'accounting.manage_accounting',
                'accounting.reports.view',
                'accounting.chart.manage',
                'accounting.invoice.manage',
                'accounting.receipt.manage',
                'accounting.warehouse.manage',
                'accounting.check.manage',
            ],
            [
                'hrm.staff.view',
                'hrm.staff.manage',
                'hrm.attendance.view',
                'hrm.attendance.manage',
                'hrm.leave.view',
                'hrm.leave.manage',
                'hrm.payroll.view',
                'hrm.payroll.manage',
                'hrm.recruitment.view',
                'hrm.recruitment.manage',
                'hrm.performance.view',
                'hrm.performance.manage',
                'hrm.training.view',
                'hrm.training.manage',
                'hrm.ess.view',
            ],
            [
                'scm.warehouse.view',
                'scm.warehouse.manage',
            ],
            [
                'sales.catalog.view',
                'sales.catalog.manage',
                'sales.campaigns.view',
                'sales.campaigns.manage',
                'sales.invoices.view',
                'sales.invoices.manage',
            ],
            [
                'docs.contracts.view',
                'docs.contracts.manage',
                'docs.files.view',
                'docs.files.manage',
            ],
            [
                'ai_content.view',
                'ai_content.manage',
            ],
            [
                'marketplace.products.view',
                'marketplace.products.manage',
                'marketplace.modules.view',
                'marketplace.modules.manage',
            ],
            [
                'integrations.sms.manage',
                'integrations.payment.manage',
                'integrations.telegram.manage',
                'integrations.bale.manage',
                'integrations.modirpayamak.view',
                'integrations.modirpayamak.manage',
            ],
            [
                'mfg.boms.view',
                'mfg.boms.manage',
                'mfg.work_orders.view',
                'mfg.work_orders.manage',
                'mfg.quality.view',
                'mfg.quality.manage',
                'mfg.planning.view',
            ],
            [
                'site_builder.catalog.view',
                'site_builder.catalog.manage',
                'site_builder.provision.view',
                'site_builder.provision.create',
                'site_builder.provision.manage',
            ],
            [
                'platform.view',
                'platform.manage',
                'platform.servers.view',
                'platform.servers.manage',
                'platform.projects.view',
                'platform.projects.manage',
                'platform.resources.view',
                'platform.resources.manage',
                'platform.deploy',
                'platform.terminal',
                'platform.settings.manage',
            ],
            [
                'marketing.site.view',
                'marketing.site.manage',
                'marketing.pages.view',
                'marketing.pages.manage',
                'marketing.blog.view',
                'marketing.blog.manage',
                'marketing.media.view',
                'marketing.media.manage',
            ],
        );
    }

    /**
     * @return list<string>
     */
    private function financeManagerPermissions(): array
    {
        return [
            'core.navigation.view',
            'core.settings.view',
            'core.dashboard.view',
            'core.dashboard.reports',
            'core.notifications.view',
            'projects.contracts.view',
            'projects.invoices.view',
            'accounting.view',
            'accounting.manage',
            'accounting.manage_accounting',
            'accounting.reports.view',
            'accounting.chart.manage',
            'accounting.invoice.manage',
            'accounting.receipt.manage',
            'accounting.warehouse.manage',
            'accounting.check.manage',
            'scm.warehouse.view',
            'scm.warehouse.manage',
            'sales.invoices.view',
            'sales.invoices.manage',
        ];
    }

    /**
     * @return list<string>
     */
    private function teamMemberPermissions(): array
    {
        return [
            'core.navigation.view',
            'core.dashboard.view',
            'core.notifications.view',
            'projects.projects.view',
            'projects.tasks.view',
            'projects.tasks.manage',
            'projects.tickets.view',
            'projects.tickets.manage',
            'projects.workload.view',
            'projects.files.view',
            'projects.files.manage',
            'hrm.attendance.view',
            'hrm.attendance.manage',
            'hrm.leave.view',
            'hrm.leave.manage',
            'hrm.ess.view',
        ];
    }

    /**
     * مدیر منابع انسانی — full HR admin plus self-service.
     *
     * @return list<string>
     */
    private function hrManagerPermissions(): array
    {
        return [
            'core.navigation.view',
            'core.dashboard.view',
            'core.notifications.view',
            'hrm.staff.view',
            'hrm.staff.manage',
            'hrm.attendance.view',
            'hrm.attendance.manage',
            'hrm.leave.view',
            'hrm.leave.manage',
            'hrm.payroll.view',
            'hrm.payroll.manage',
            'hrm.recruitment.view',
            'hrm.recruitment.manage',
            'hrm.performance.view',
            'hrm.performance.manage',
            'hrm.training.view',
            'hrm.training.manage',
            'hrm.ess.view',
        ];
    }

    /**
     * @return list<string>
     */
    private function salesConsultantPermissions(): array
    {
        return [
            'core.navigation.view',
            'core.dashboard.view',
            'core.notifications.view',
            'crm.leads.view',
            'crm.leads.manage',
            'crm.accounts.view',
            'crm.accounts.manage',
            'crm.deals.view',
            'crm.deals.manage',
            'crm.contacts.view',
            'crm.contacts.manage',
            'projects.contracts.view',
            'projects.contracts.manage',
            'sales.catalog.view',
            'sales.campaigns.view',
            'sales.invoices.view',
            'sales.invoices.manage',
            'crm.activities.view',
            'crm.activities.manage',
            'crm.messages.view',
            'crm.messages.manage',
            'crm.forecast.view',
            'crm.audiences.view',
            'crm.audiences.manage',
            'crm.audit.view',
        ];
    }

    /**
     * @return list<string>
     */
    private function clientPermissions(): array
    {
        return [
            'core.navigation.view',
            'core.dashboard.view',
            'core.notifications.view',
            'projects.projects.view',
            'projects.contracts.view',
            'projects.invoices.view',
            'projects.tickets.view',
            'projects.tickets.manage',
            'projects.appointments.view',
            'portal.access',
        ];
    }

    /**
     * @return list<string>
     */
    private function crmSpecialistPermissions(): array
    {
        return [
            'core.navigation.view',
            'core.dashboard.view',
            'core.notifications.view',
            'crm.leads.view',
            'crm.leads.manage',
            'crm.accounts.view',
            'crm.accounts.manage',
            'crm.contacts.view',
            'crm.contacts.manage',
            'crm.deals.view',
            'crm.deals.manage',
            'crm.consultations.view',
            'crm.consultations.manage',
            'crm.campaigns.view',
            'crm.campaigns.manage',
            'crm.services.view',
            'crm.services.manage',
            'crm.staff.view',
            'crm.tickets.view',
            'crm.tickets.manage',
            'projects.projects.view',
            'projects.tickets.view',
            'projects.tickets.manage',
            'projects.appointments.view',
            'projects.appointments.manage',
            'crm.activities.view',
            'crm.activities.manage',
            'crm.messages.view',
            'crm.messages.manage',
            'crm.forecast.view',
            'crm.audiences.view',
            'crm.audiences.manage',
            'crm.audit.view',
        ];
    }

    /**
     * @return list<string>
     */
    private function projectManagerPermissions(): array
    {
        return [
            'core.navigation.view',
            'core.dashboard.view',
            'core.notifications.view',
            'crm.accounts.view',
            'crm.contacts.view',
            'projects.projects.view',
            'projects.projects.manage',
            'projects.contracts.view',
            'projects.contracts.manage',
            'projects.tasks.view',
            'projects.tasks.manage',
            'projects.tickets.view',
            'projects.tickets.manage',
            'projects.appointments.view',
            'projects.appointments.manage',
            'projects.invoices.view',
            'projects.invoices.manage',
            'projects.import_export',
            'projects.workload.view',
            'projects.files.view',
            'projects.files.manage',
            'projects.audit.view',
        ];
    }
}
