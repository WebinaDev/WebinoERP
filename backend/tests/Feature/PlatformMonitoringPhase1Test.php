<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Entities\SystemModule;
use Modules\Platform\Database\Seeders\PlatformAlertRulesSeeder;
use Modules\Platform\Entities\PlatformAlertEvent;
use Modules\Platform\Entities\PlatformAlertRule;
use Modules\Platform\Entities\PlatformMetricSnapshot;
use Modules\Platform\Entities\PlatformServer;
use Modules\Platform\Services\PlatformAlertEvaluator;
use Modules\Platform\Services\PlatformServerMetricsCollector;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class PlatformMonitoringPhase1Test extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        SystemModule::query()->firstOrCreate(
            ['slug' => 'platform'],
            ['name' => 'Platform', 'is_active' => true]
        );
    }

    /**
     * 1. Test storing metric snapshots and retrieving server metrics.
     */
    public function test_metric_snapshots_storage_and_query(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $server = PlatformServer::query()->create([
            'name' => 'monitoring-node-1',
            'ip' => '10.0.0.1',
            'port' => 22,
            'user' => 'root',
            'status' => 'ready',
            'is_localhost' => false,
        ]);

        $now = Carbon::now();

        // Insert test snapshots
        PlatformMetricSnapshot::query()->create([
            'server_id' => $server->id,
            'scope' => 'server',
            'metric_key' => 'cpu.usage',
            'value' => 45.5,
            'unit' => 'percent',
            'collected_at' => $now->copy()->subMinutes(2),
        ]);

        PlatformMetricSnapshot::query()->create([
            'server_id' => $server->id,
            'scope' => 'server',
            'metric_key' => 'mem.usage_percent',
            'value' => 62.1,
            'unit' => 'percent',
            'collected_at' => $now->copy()->subMinutes(1),
        ]);

        $this->assertDatabaseHas('platform_metric_snapshots', [
            'server_id' => $server->id,
            'metric_key' => 'cpu.usage',
            'value' => 45.5,
        ]);

        // GET /api/v1/platform/servers/{id}/metrics
        $res = $this->getJson("/api/v1/platform/servers/{$server->id}/metrics");
        $res->assertOk();
        $res->assertJsonPath('data.server_id', $server->id);
        $this->assertCount(2, $res->json('data.metrics'));
    }

    /**
     * 2. Test monitoring overview API.
     */
    public function test_monitoring_overview_api(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $server1 = PlatformServer::query()->create([
            'name' => 'app-srv-01',
            'ip' => '10.0.0.1',
            'port' => 22,
            'user' => 'root',
            'status' => 'ready',
        ]);

        $server2 = PlatformServer::query()->create([
            'name' => 'db-srv-02',
            'ip' => '10.0.0.2',
            'port' => 22,
            'user' => 'root',
            'status' => 'unreachable',
        ]);

        // Seed snapshot
        PlatformMetricSnapshot::query()->create([
            'server_id' => $server1->id,
            'scope' => 'server',
            'metric_key' => 'cpu.usage',
            'value' => 20.0,
            'unit' => 'percent',
            'collected_at' => now(),
        ]);

        $res = $this->getJson('/api/v1/platform/monitoring/overview');
        $res->assertOk();
        $res->assertJsonStructure([
            'data' => [
                'counts' => [
                    'total_servers',
                    'healthy_servers',
                    'warning_servers',
                    'critical_servers',
                    'open_alerts',
                ],
                'servers',
            ],
        ]);

        $this->assertEquals(2, $res->json('data.counts.total_servers'));
        $this->assertEquals(1, $res->json('data.counts.critical_servers')); // server2 is unreachable
        $this->assertEquals(1, $res->json('data.counts.healthy_servers')); // server1 is healthy
    }

    /**
     * 3. Test alert creation when threshold is breached (with seeded rules and evaluator).
     */
    public function test_alert_rule_evaluator_triggers_and_deduplicates(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $this->seed(PlatformAlertRulesSeeder::class);

        $server = PlatformServer::query()->create([
            'name' => 'critical-worker',
            'ip' => '10.0.0.5',
            'port' => 22,
            'user' => 'root',
            'status' => 'ready',
        ]);

        // Find the critical disk rule: disk.usage_percent > 95 for 60 seconds
        $rule = PlatformAlertRule::query()->where('name', 'Critical Disk Space Depletion')->firstOrFail();

        // Create snapshots breaching 95% over the last 60 seconds
        PlatformMetricSnapshot::query()->create([
            'server_id' => $server->id,
            'metric_key' => 'disk.usage_percent',
            'value' => 97.5,
            'unit' => 'percent',
            'collected_at' => Carbon::now()->subSeconds(30),
        ]);

        /** @var PlatformAlertEvaluator $evaluator */
        $evaluator = app(PlatformAlertEvaluator::class);
        $evaluator->evaluateServer($server);

        // Assert alert event was created
        $this->assertDatabaseHas('platform_alert_events', [
            'rule_id' => $rule->id,
            'server_id' => $server->id,
            'status' => 'open',
            'severity' => 'critical',
        ]);

        $event = PlatformAlertEvent::query()->where('server_id', $server->id)->first();
        $this->assertNotNull($event);
        $this->assertEquals('open', $event->status);

        // Run evaluator again to test deduplication: it should NOT create another open event
        $evaluator->evaluateServer($server);
        $this->assertEquals(1, PlatformAlertEvent::query()->where('server_id', $server->id)->count());

        // Test acknowledge alert API
        $ackRes = $this->postJson("/api/v1/platform/monitoring/alerts/{$event->id}/ack");
        $ackRes->assertOk();
        $this->assertDatabaseHas('platform_alert_events', [
            'id' => $event->id,
            'status' => 'acknowledged',
        ]);

        // Test resolve alert API
        $resRes = $this->postJson("/api/v1/platform/monitoring/alerts/{$event->id}/resolve");
        $resRes->assertOk();
        $this->assertDatabaseHas('platform_alert_events', [
            'id' => $event->id,
            'status' => 'resolved',
        ]);
    }
}
