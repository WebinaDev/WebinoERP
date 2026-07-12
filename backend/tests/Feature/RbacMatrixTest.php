<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Database\Seeders\RolesAndPermissionsSeeder;
use Modules\Core\Entities\SystemModule;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class RbacMatrixTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->seedLicensedModules();
    }

    public function test_client_cannot_access_admin_core_endpoints(): void
    {
        $user = $this->actingAsRole(RolesAndPermissionsSeeder::ROLE_CLIENT);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/core/settings')->assertForbidden();
        $this->getJson('/api/v1/core/users')->assertForbidden();
        $this->getJson('/api/v1/core/licenses')->assertForbidden();
        $this->getJson('/api/v1/crm/leads')->assertForbidden();
        $this->getJson('/api/v1/accounting/journals')->assertForbidden();
    }

    public function test_sales_consultant_can_view_crm_but_not_core_settings(): void
    {
        $user = $this->actingAsRole(RolesAndPermissionsSeeder::ROLE_SALES_CONSULTANT);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/crm/leads')->assertOk();
        $this->getJson('/api/v1/crm/deals')->assertOk();
        $this->getJson('/api/v1/core/settings')->assertForbidden();
        $this->getJson('/api/v1/crm/sources')->assertOk();
    }

    public function test_finance_manager_can_access_accounting_not_hrm_staff(): void
    {
        $user = $this->actingAsRole(RolesAndPermissionsSeeder::ROLE_FINANCE_MANAGER);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/accounting/journals')->assertOk();
        $this->getJson('/api/v1/accounting/chart')->assertOk();
        $this->getJson('/api/v1/hrm/employees')->assertForbidden();
        $this->getJson('/api/v1/scm/inbound')->assertForbidden();
    }

    public function test_team_member_can_access_projects_and_hrm_attendance(): void
    {
        $user = $this->actingAsRole(RolesAndPermissionsSeeder::ROLE_TEAM_MEMBER);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/projects/projects')->assertOk();
        $this->getJson('/api/v1/hrm/attendance')->assertOk();
        $this->getJson('/api/v1/crm/leads')->assertForbidden();
        $this->getJson('/api/v1/marketplace/modules')->assertForbidden();
    }

    public function test_system_manager_has_full_access_sample(): void
    {
        $user = $this->actingAsRole(RolesAndPermissionsSeeder::ROLE_SYSTEM_MANAGER);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/core/settings')->assertOk();
        $this->getJson('/api/v1/hrm/employees')->assertOk();
        $this->getJson('/api/v1/docs/contracts')->assertOk();
        $this->getJson('/api/v1/sales/invoices')->assertOk();
        $this->getJson('/api/v1/marketplace/modules')->assertOk();
    }
}
