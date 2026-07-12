<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Database\Seeders\RolesAndPermissionsSeeder;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class CoreReportsApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_system_manager_can_fetch_reports_with_series(): void
    {
        $user = $this->actingAsRole(RolesAndPermissionsSeeder::ROLE_SYSTEM_MANAGER);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/core/reports?from=2026-01-01&to=2026-12-31');
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'range',
                    'contracts_total',
                    'tasks_completed',
                    'leads_new',
                    'tickets_closed',
                    'sprints_started',
                    'series' => [
                        'contracts',
                        'tasks_completed',
                        'leads',
                        'tickets_closed',
                        'sprints',
                    ],
                ],
            ]);
    }

    public function test_reports_csv_export(): void
    {
        $user = $this->actingAsRole(RolesAndPermissionsSeeder::ROLE_SYSTEM_MANAGER);
        Sanctum::actingAs($user);

        $response = $this->get('/api/v1/core/reports/export.csv?from=2026-01-01&to=2026-12-31');
        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
    }
}
