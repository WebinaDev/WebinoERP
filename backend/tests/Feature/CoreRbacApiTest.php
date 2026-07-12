<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Database\Seeders\RolesAndPermissionsSeeder;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class CoreRbacApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_client_forbidden_on_users_settings_licenses(): void
    {
        $user = $this->actingAsRole(RolesAndPermissionsSeeder::ROLE_CLIENT);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/core/users')->assertForbidden();
        $this->getJson('/api/v1/core/settings')->assertForbidden();
        $this->getJson('/api/v1/core/licenses')->assertForbidden();
        $this->patchJson('/api/v1/core/settings', ['app_name' => 'X'])->assertForbidden();
    }

    public function test_system_manager_can_access_admin_endpoints(): void
    {
        $user = $this->actingAsRole(RolesAndPermissionsSeeder::ROLE_SYSTEM_MANAGER);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/core/users')->assertOk();
        $this->getJson('/api/v1/core/settings')->assertOk();
        $this->getJson('/api/v1/core/licenses')->assertOk();
    }

    public function test_auth_user_returns_roles_and_permissions(): void
    {
        $user = $this->actingAsRole(RolesAndPermissionsSeeder::ROLE_SALES_CONSULTANT);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/core/auth/user');
        $response->assertOk();
        $response->assertJsonPath('data.roles.0', RolesAndPermissionsSeeder::ROLE_SALES_CONSULTANT);
        $this->assertContains('crm.leads.view', $response->json('data.permissions'));
        $response->assertJsonStructure(['data' => ['licensed_modules', 'active_modules']]);
    }

    public function test_admin_logs_visitor_stats_and_reports_smoke(): void
    {
        $user = $this->actingAsRole(RolesAndPermissionsSeeder::ROLE_SYSTEM_MANAGER);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/core/logs')->assertOk();
        $this->getJson('/api/v1/core/visitor-stats')->assertOk();
        $this->getJson('/api/v1/core/reports?from=2026-01-01&to=2026-12-31')->assertOk();
    }
}
