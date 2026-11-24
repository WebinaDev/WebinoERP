<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Entities\SystemModule;
use Modules\Platform\Entities\PlatformEnvironment;
use Modules\Platform\Entities\PlatformMetricRollupHourly;
use Modules\Platform\Entities\PlatformMetricSnapshot;
use Modules\Platform\Entities\PlatformProject;
use Modules\Platform\Entities\PlatformResource;
use Modules\Platform\Entities\PlatformResourceQuota;
use Modules\Platform\Entities\PlatformServer;
use Modules\Platform\Jobs\PurgeOldMetricsJob;
use Modules\Platform\Jobs\RollupMetricsHourlyJob;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class PlatformMonitoringPhase4Test extends TestCase
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
     * 1. Test RollupMetricsHourlyJob calculates avg, max, min, sample_count.
     */
    public function test_hourly_metrics_rollup(): void
    {
        $server = PlatformServer::query()->create([
            'name' => 'node-rollup-01',
            'ip' => '127.0.0.1',
            'port' => 22,
            'user' => 'root',
            'status' => 'ready',
        ]);

        $hour = Carbon::now()->subHours(2)->startOfHour();

        // Seed 3 raw snapshots in that hour
        PlatformMetricSnapshot::query()->create([
            'server_id' => $server->id,
            'scope' => 'server',
            'metric_key' => 'cpu.usage',
            'value' => 20.0,
            'unit' => 'percent',
            'collected_at' => $hour->copy()->addMinutes(10),
        ]);

        PlatformMetricSnapshot::query()->create([
            'server_id' => $server->id,
            'scope' => 'server',
            'metric_key' => 'cpu.usage',
            'value' => 50.0,
            'unit' => 'percent',
            'collected_at' => $hour->copy()->addMinutes(25),
        ]);

        PlatformMetricSnapshot::query()->create([
            'server_id' => $server->id,
            'scope' => 'server',
            'metric_key' => 'cpu.usage',
            'value' => 80.0,
            'unit' => 'percent',
            'collected_at' => $hour->copy()->addMinutes(40),
        ]);

        // Run Hourly Job
        $job = new RollupMetricsHourlyJob($hour);
        $job->handle();

        $rollup = PlatformMetricRollupHourly::query()
            ->where('server_id', $server->id)
            ->where('metric_key', 'cpu.usage')
            ->first();

        $this->assertNotNull($rollup);
        $this->assertEquals(50.0, (float) $rollup->value_avg);
        $this->assertEquals(80.0, (float) $rollup->value_max);
        $this->assertEquals(20.0, (float) $rollup->value_min);
        $this->assertEquals(3, (int) $rollup->sample_count);
    }

    /**
     * 2. Test Top Consumers API.
     */
    public function test_top_consumers_report(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $server = PlatformServer::query()->create(['name' => 'srv-01', 'ip' => '127.0.0.1']);
        $project = PlatformProject::query()->create(['name' => 'Consumer Project']);
        $env = PlatformEnvironment::query()->create(['project_id' => $project->id, 'name' => 'prod']);

        $res1 = PlatformResource::query()->create([
            'environment_id' => $env->id,
            'server_id' => $server->id,
            'type' => 'application',
            'name' => 'low-cpu-app',
        ]);

        $res2 = PlatformResource::query()->create([
            'environment_id' => $env->id,
            'server_id' => $server->id,
            'type' => 'application',
            'name' => 'high-cpu-app',
        ]);

        PlatformMetricSnapshot::query()->create([
            'server_id' => $server->id,
            'project_id' => $project->id,
            'resource_id' => $res1->id,
            'scope' => 'resource',
            'metric_key' => 'cpu.usage',
            'value' => 15.0,
            'unit' => 'percent',
            'collected_at' => now(),
        ]);

        PlatformMetricSnapshot::query()->create([
            'server_id' => $server->id,
            'project_id' => $project->id,
            'resource_id' => $res2->id,
            'scope' => 'resource',
            'metric_key' => 'cpu.usage',
            'value' => 88.0,
            'unit' => 'percent',
            'collected_at' => now(),
        ]);

        // GET /api/v1/platform/monitoring/reports/top-consumers?metric=cpu&limit=5
        $res = $this->getJson('/api/v1/platform/monitoring/reports/top-consumers?metric=cpu&limit=5');
        $res->assertOk();
        $consumers = $res->json('data.consumers');
        $this->assertCount(2, $consumers);
        $this->assertEquals($res2->id, $consumers[0]['resource_id']); // Highest first
        $this->assertEquals(88.0, $consumers[0]['value']);
    }

    /**
     * 3. Test Quota Risk API.
     */
    public function test_quota_risk_report(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $server = PlatformServer::query()->create(['name' => 'srv-01', 'ip' => '127.0.0.1']);
        $project = PlatformProject::query()->create(['name' => 'Risk Project']);
        $env = PlatformEnvironment::query()->create(['project_id' => $project->id, 'name' => 'prod']);

        $resource = PlatformResource::query()->create([
            'environment_id' => $env->id,
            'server_id' => $server->id,
            'type' => 'application',
            'name' => 'risky-app',
        ]);

        PlatformResourceQuota::query()->create([
            'resource_id' => $resource->id,
            'disk_mb_soft' => 100.0,
            'disk_mb_hard' => 200.0,
            'enforce_action' => 'throttle',
            'is_enabled' => true,
        ]);

        // Seed snapshot exceeding soft limit (150 MB)
        PlatformMetricSnapshot::query()->create([
            'server_id' => $server->id,
            'project_id' => $project->id,
            'resource_id' => $resource->id,
            'scope' => 'resource',
            'metric_key' => 'disk.used',
            'value' => 150 * 1024 * 1024,
            'unit' => 'bytes',
            'collected_at' => now(),
        ]);

        $res = $this->getJson('/api/v1/platform/monitoring/reports/quota-risk');
        $res->assertOk();
        $this->assertGreaterThanOrEqual(1, $res->json('data.total_risks'));
        $this->assertEquals('risky-app', $res->json('data.risks.0.name'));
        $this->assertEquals('soft_warning', $res->json('data.risks.0.risk_level'));
    }

    /**
     * 4. Test PurgeOldMetricsJob deletes records exceeding retention window.
     */
    public function test_purge_old_metrics_job(): void
    {
        $server = PlatformServer::query()->create(['name' => 'srv-old', 'ip' => '127.0.0.1']);

        // Snapshot 5 days ago (retention: 3 days)
        PlatformMetricSnapshot::query()->create([
            'server_id' => $server->id,
            'scope' => 'server',
            'metric_key' => 'cpu.usage',
            'value' => 10.0,
            'unit' => 'percent',
            'collected_at' => Carbon::now()->subDays(5),
        ]);

        // Snapshot 1 day ago (should remain)
        PlatformMetricSnapshot::query()->create([
            'server_id' => $server->id,
            'scope' => 'server',
            'metric_key' => 'cpu.usage',
            'value' => 20.0,
            'unit' => 'percent',
            'collected_at' => Carbon::now()->subDay(),
        ]);

        $this->assertEquals(2, PlatformMetricSnapshot::query()->count());

        $purge = new PurgeOldMetricsJob();
        $purge->handle();

        $this->assertEquals(1, PlatformMetricSnapshot::query()->count());
    }
}
