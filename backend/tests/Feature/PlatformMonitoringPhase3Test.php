<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Entities\SystemModule;
use Modules\Platform\Entities\PlatformAlertEvent;
use Modules\Platform\Entities\PlatformAlertRule;
use Modules\Platform\Entities\PlatformEnvironment;
use Modules\Platform\Entities\PlatformMetricSnapshot;
use Modules\Platform\Entities\PlatformProject;
use Modules\Platform\Entities\PlatformResource;
use Modules\Platform\Entities\PlatformResourceQuota;
use Modules\Platform\Entities\PlatformServer;
use Modules\Platform\Entities\PlatformServiceHealth;
use Modules\Platform\Services\PlatformQuotaEnforcer;
use Modules\Platform\Services\PlatformServiceHealthCollector;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class PlatformMonitoringPhase3Test extends TestCase
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
     * 1. Test Service Health down triggers critical alert.
     */
    public function test_service_health_down_triggers_alert(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $server = PlatformServer::query()->create([
            'name' => 'edge-srv-01',
            'ip' => '127.0.0.1',
            'port' => 22,
            'user' => 'root',
            'status' => 'ready',
        ]);

        /** @var PlatformServiceHealthCollector $collector */
        $collector = app(PlatformServiceHealthCollector::class);

        // Run collector on local server
        $res = $collector->collect($server);

        $this->assertArrayHasKey('services', $res);
        $this->assertDatabaseHas('platform_service_health', [
            'server_id' => $server->id,
            'service_key' => 'caddy',
        ]);

        // Simulate a service down explicitly
        $ref = new \ReflectionClass($collector);
        $method = $ref->getMethod('raiseServiceDownAlert');
        $method->setAccessible(true);
        $method->invoke($collector, $server, 'caddy', 'Caddy test process dead');

        $this->assertDatabaseHas('platform_alert_events', [
            'server_id' => $server->id,
            'status' => 'open',
            'severity' => 'critical',
        ]);

        // GET /api/v1/platform/servers/{id}/services/health
        $healthRes = $this->getJson("/api/v1/platform/servers/{$server->id}/services/health");
        $healthRes->assertOk();
        $healthRes->assertJsonPath('data.server_id', $server->id);
    }

    /**
     * 2. Test Hard Quota throttle blocks new deployment.
     */
    public function test_hard_quota_throttle_blocks_deployment(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $project = PlatformProject::query()->create(['name' => 'Store App']);
        $env = PlatformEnvironment::query()->create(['project_id' => $project->id, 'name' => 'prod']);
        $server = PlatformServer::query()->create(['name' => 'srv-01', 'ip' => '127.0.0.1']);
        $resource = PlatformResource::query()->create([
            'environment_id' => $env->id,
            'server_id' => $server->id,
            'type' => 'application',
            'name' => 'big-site',
        ]);

        // Quota: Hard disk 100 MB, Action: throttle
        PlatformResourceQuota::query()->create([
            'resource_id' => $resource->id,
            'disk_mb_soft' => 50.0,
            'disk_mb_hard' => 100.0,
            'enforce_action' => 'throttle',
            'is_enabled' => true,
        ]);

        // Create snapshot exceeding hard quota (120 MB)
        PlatformMetricSnapshot::query()->create([
            'server_id' => $server->id,
            'project_id' => $project->id,
            'resource_id' => $resource->id,
            'scope' => 'resource',
            'metric_key' => 'disk.used',
            'value' => 120 * 1024 * 1024,
            'unit' => 'bytes',
            'collected_at' => now(),
        ]);

        // Attempting to deploy should fail with RuntimeException (500 or 400 error caught)
        try {
            $deployRes = $this->postJson("/api/v1/platform/resources/{$resource->id}/deploy");
            $deployRes->assertStatus(500);
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Throttle Enforcement', $e->getMessage());
        }
    }

    /**
     * 3. Test Hard Quota suspend action marks resource suspended.
     */
    public function test_hard_quota_suspend_action_marks_resource_suspended(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $project = PlatformProject::query()->create(['name' => 'Store App 2']);
        $env = PlatformEnvironment::query()->create(['project_id' => $project->id, 'name' => 'prod']);
        $server = PlatformServer::query()->create(['name' => 'srv-02', 'ip' => '127.0.0.1']);
        $resource = PlatformResource::query()->create([
            'environment_id' => $env->id,
            'server_id' => $server->id,
            'type' => 'application',
            'name' => 'abusive-worker',
        ]);

        /** @var PlatformQuotaEnforcer $enforcer */
        $enforcer = app(PlatformQuotaEnforcer::class);
        $enforcer->suspendResource($resource, 'Over memory quota hard limit');

        $resource->refresh();
        $this->assertEquals('suspended', $resource->status);
        $this->assertTrue((bool) ($resource->settings['is_suspended'] ?? false));

        // When suspended, ensure deployment is blocked
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('استقرار متوقف شد');
        $enforcer->ensureDeployAllowed($resource);
    }
}
