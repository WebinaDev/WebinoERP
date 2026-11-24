<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Entities\SystemModule;
use Modules\Platform\Entities\PlatformAlertEvent;
use Modules\Platform\Entities\PlatformEnvironment;
use Modules\Platform\Entities\PlatformMetricSnapshot;
use Modules\Platform\Entities\PlatformProject;
use Modules\Platform\Entities\PlatformProjectQuota;
use Modules\Platform\Entities\PlatformResource;
use Modules\Platform\Entities\PlatformResourceQuota;
use Modules\Platform\Entities\PlatformServer;
use Modules\Platform\Services\PlatformResourceUsageCollector;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class PlatformMonitoringPhase2Test extends TestCase
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
     * 1. Test storing resource snapshots & querying resource metrics.
     */
    public function test_resource_metrics_storage_and_query(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $server = PlatformServer::query()->create([
            'name' => 'srv-01',
            'ip' => '127.0.0.1',
            'port' => 22,
            'user' => 'root',
            'status' => 'ready',
        ]);

        $project = PlatformProject::query()->create(['name' => 'Store Project']);
        $env = PlatformEnvironment::query()->create([
            'project_id' => $project->id,
            'name' => 'production',
        ]);

        $resource = PlatformResource::query()->create([
            'environment_id' => $env->id,
            'server_id' => $server->id,
            'type' => 'application',
            'name' => 'ecommerce-store',
        ]);

        // Insert resource snapshots
        PlatformMetricSnapshot::query()->create([
            'server_id' => $server->id,
            'project_id' => $project->id,
            'resource_id' => $resource->id,
            'scope' => 'resource',
            'metric_key' => 'disk.used',
            'value' => 524288000, // 500 MB in bytes
            'unit' => 'bytes',
            'collected_at' => now(),
        ]);

        $res = $this->getJson("/api/v1/platform/resources/{$resource->id}/metrics");
        $res->assertOk();
        $res->assertJsonPath('data.resource_id', $resource->id);
        $this->assertEquals(524288000, $res->json('data.latest.disk_bytes'));
    }

    /**
     * 2. Test Resource Quota GET & PUT.
     */
    public function test_resource_quota_get_and_put(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $project = PlatformProject::query()->create(['name' => 'Project Alpha']);
        $env = PlatformEnvironment::query()->create(['project_id' => $project->id, 'name' => 'prod']);
        $server = PlatformServer::query()->create(['name' => 'srv-01', 'ip' => '127.0.0.1']);
        $resource = PlatformResource::query()->create([
            'environment_id' => $env->id,
            'server_id' => $server->id,
            'type' => 'application',
            'name' => 'frontend-app',
        ]);

        // GET default quota
        $getRes = $this->getJson("/api/v1/platform/resources/{$resource->id}/quota");
        $getRes->assertOk();

        // PUT custom quota
        $putRes = $this->putJson("/api/v1/platform/resources/{$resource->id}/quota", [
            'disk_mb_soft' => 2048.0,
            'disk_mb_hard' => 4096.0,
            'ram_mb_soft' => 512.0,
            'ram_mb_hard' => 1024.0,
            'cpu_percent_soft' => 70.0,
            'cpu_percent_hard' => 90.0,
            'inode_soft' => 50000,
            'inode_hard' => 80000,
            'enforce_action' => 'throttle',
            'is_enabled' => true,
        ]);

        $putRes->assertOk();
        $this->assertDatabaseHas('platform_resource_quotas', [
            'resource_id' => $resource->id,
            'disk_mb_soft' => 2048.0,
            'disk_mb_hard' => 4096.0,
            'enforce_action' => 'throttle',
        ]);
    }

    /**
     * 3. Test Project Usage Aggregation & Quota GET & PUT.
     */
    public function test_project_usage_and_quota_lifecycle(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $project = PlatformProject::query()->create(['name' => 'Project Big']);
        $env = PlatformEnvironment::query()->create(['project_id' => $project->id, 'name' => 'prod']);
        $server = PlatformServer::query()->create(['name' => 'srv-01', 'ip' => '127.0.0.1']);
        $r1 = PlatformResource::query()->create([
            'environment_id' => $env->id,
            'server_id' => $server->id,
            'type' => 'application',
            'name' => 'api-svc',
        ]);
        $r2 = PlatformResource::query()->create([
            'environment_id' => $env->id,
            'server_id' => $server->id,
            'type' => 'database',
            'name' => 'db-svc',
        ]);

        // Snapshots for r1 and r2
        $now = now();
        PlatformMetricSnapshot::query()->create([
            'server_id' => $server->id,
            'project_id' => $project->id,
            'resource_id' => $r1->id,
            'scope' => 'resource',
            'metric_key' => 'disk.used',
            'value' => 104857600, // 100MB
            'unit' => 'bytes',
            'collected_at' => $now,
        ]);
        PlatformMetricSnapshot::query()->create([
            'server_id' => $server->id,
            'project_id' => $project->id,
            'resource_id' => $r2->id,
            'scope' => 'resource',
            'metric_key' => 'disk.used',
            'value' => 314572800, // 300MB
            'unit' => 'bytes',
            'collected_at' => $now,
        ]);

        // Aggregate project
        /** @var PlatformResourceUsageCollector $collector */
        $collector = app(PlatformResourceUsageCollector::class);
        $agg = $collector->aggregateProject($project);

        $this->assertEquals(419430400, $agg['disk_bytes']); // 400 MB

        // GET /api/v1/platform/projects/{id}/usage
        $res = $this->getJson("/api/v1/platform/projects/{$project->id}/usage");
        $res->assertOk();
        $this->assertEquals(419430400, $res->json('data.total_usage.disk_bytes'));
        $this->assertCount(2, $res->json('data.resources'));

        // PUT Project Quota
        $putQuota = $this->putJson("/api/v1/platform/projects/{$project->id}/quota", [
            'disk_mb_soft' => 10240.0,
            'disk_mb_hard' => 20480.0,
            'enforce_action' => 'alert',
            'is_enabled' => true,
        ]);
        $putQuota->assertOk();
        $this->assertDatabaseHas('platform_project_quotas', [
            'project_id' => $project->id,
            'disk_mb_soft' => 10240.0,
        ]);
    }

    /**
     * 4. Test Alert Triggering on Soft and Hard Quota Breach.
     */
    public function test_quota_alert_triggers_on_soft_and_hard_breach(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $project = PlatformProject::query()->create(['name' => 'Quota Test']);
        $env = PlatformEnvironment::query()->create(['project_id' => $project->id, 'name' => 'prod']);
        $server = PlatformServer::query()->create(['name' => 'srv-01', 'ip' => '127.0.0.1']);
        $resource = PlatformResource::query()->create([
            'environment_id' => $env->id,
            'server_id' => $server->id,
            'type' => 'application',
            'name' => 'heavy-app',
        ]);

        // Set quota: Soft 100 MB, Hard 200 MB
        PlatformResourceQuota::query()->create([
            'resource_id' => $resource->id,
            'disk_mb_soft' => 100.0,
            'disk_mb_hard' => 200.0,
            'enforce_action' => 'alert',
            'is_enabled' => true,
        ]);

        /** @var PlatformResourceUsageCollector $collector */
        $collector = app(PlatformResourceUsageCollector::class);

        // Sub-test A: Breach Soft Quota (150 MB)
        $collector->collectResource($resource); // Will get ~0 bytes by default
        // Simulate soft quota breach by calling checkThreshold or evaluate
        $ref = new \ReflectionClass($collector);
        $method = $ref->getMethod('evaluateResourceQuota');
        $method->setAccessible(true);

        // Soft breach (150 MB)
        $method->invoke($collector, $resource, [
            'disk_used' => 150 * 1024 * 1024,
            'mem_used' => 0,
            'cpu_usage' => 0,
            'inode_used' => 10,
            'source' => 'test',
        ], $server->id, $project->id);

        $this->assertDatabaseHas('platform_alert_events', [
            'resource_id' => $resource->id,
            'status' => 'open',
            'severity' => 'warning',
        ]);

        // Sub-test B: Breach Hard Quota (250 MB)
        $method->invoke($collector, $resource, [
            'disk_used' => 250 * 1024 * 1024,
            'mem_used' => 0,
            'cpu_usage' => 0,
            'inode_used' => 10,
            'source' => 'test',
        ], $server->id, $project->id);

        $this->assertDatabaseHas('platform_alert_events', [
            'resource_id' => $resource->id,
            'status' => 'open',
            'severity' => 'critical',
        ]);
    }
}
