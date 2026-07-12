<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Database\Seeders\RolesAndPermissionsSeeder;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class CoreVisitorStatsApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_track_records_visit(): void
    {
        $this->postJson('/api/v1/core/visitor-stats/track', [
            'path' => '/dashboard',
            'title' => 'Dashboard',
            'session_id' => 'sess-1',
        ])->assertOk()->assertJsonPath('data.recorded', true);

        $this->assertDatabaseHas('core_visits', ['landing_path' => '/dashboard']);
    }

    public function test_index_uses_aggregate_table_when_available(): void
    {
        $day = now()->subDay()->toDateString();
        DB::table('core_visitor_daily')->insert([
            'date' => $day,
            'uniques' => 5,
            'visits' => 10,
            'pageviews' => 20,
            'avg_session_ms' => 1000,
            'updated_at' => now(),
        ]);

        $user = $this->actingAsRole(RolesAndPermissionsSeeder::ROLE_SYSTEM_MANAGER);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/core/visitor-stats?days=7');
        $response->assertOk()
            ->assertJsonPath('data.data_source', 'aggregate')
            ->assertJsonPath('data.total_visits', 10)
            ->assertJsonPath('data.pageviews', 20);
    }

    public function test_index_falls_back_to_raw_when_no_aggregate(): void
    {
        DB::table('core_visits')->insert([
            'session_id' => 's1',
            'ip' => '127.0.0.1',
            'user_agent' => 'Test',
            'device' => 'Desktop',
            'landing_path' => '/',
            'created_at' => now(),
        ]);

        $user = $this->actingAsRole(RolesAndPermissionsSeeder::ROLE_SYSTEM_MANAGER);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/core/visitor-stats?days=7');
        $response->assertOk()
            ->assertJsonPath('data.data_source', 'raw')
            ->assertJsonPath('data.total_visits', 1);
    }
}
