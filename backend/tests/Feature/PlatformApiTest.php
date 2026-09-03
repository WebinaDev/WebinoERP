<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Entities\SystemModule;
use Modules\Platform\Entities\PlatformResource;
use Modules\Platform\Entities\PlatformServer;
use Modules\Platform\Support\SiteTypeProfiles;
use Modules\SiteBuilder\Database\Seeders\SiteBuilderSeeder;
use Modules\SiteBuilder\Entities\WebinoPackage;
use Modules\SiteBuilder\Services\LicenseProvisionerService;
use Modules\SiteBuilder\Services\SiteProvisionOrchestrator;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class PlatformApiTest extends TestCase
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
        SystemModule::query()->firstOrCreate(
            ['slug' => 'site_builder'],
            ['name' => 'Site Builder', 'is_active' => true]
        );
    }

    public function test_dashboard_servers_projects_tokens(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/platform/dashboard')->assertOk();

        $key = $this->postJson('/api/v1/platform/ssh-keys', [
            'name' => 'local',
            'private_key' => "-----BEGIN OPENSSH PRIVATE KEY-----\ntest\n-----END OPENSSH PRIVATE KEY-----",
            'public_key' => 'ssh-ed25519 AAAA test',
        ])->assertCreated();

        $server = $this->postJson('/api/v1/platform/servers', [
            'name' => 'localhost',
            'ip' => '127.0.0.1',
            'user' => 'root',
            'port' => 22,
            'ssh_key_id' => $key->json('data.id'),
            'is_localhost' => true,
        ])->assertCreated();

        $this->getJson('/api/v1/platform/servers/'.$server->json('data.id'))->assertOk();

        $project = $this->postJson('/api/v1/platform/projects', [
            'name' => 'Demo Project',
            'crm_account_id' => null,
        ])->assertCreated();

        $envId = $project->json('data.environments.0.id');
        $this->assertNotNull($envId);

        $resource = $this->postJson('/api/v1/platform/resources', [
            'environment_id' => $envId,
            'server_id' => $server->json('data.id'),
            'type' => 'database',
            'name' => 'cafe-db',
            'database_type' => 'postgresql',
        ])->assertCreated();

        $this->assertSame('database', $resource->json('data.type'));
        $this->assertNotEmpty($resource->json('data.settings.deploy_webhook_token'));

        $clone = $this->postJson('/api/v1/platform/resources/'.$resource->json('data.id').'/clone', [
            'name' => 'cafe-db-copy',
        ])->assertCreated();
        $this->assertSame('cafe-db-copy', $clone->json('data.name'));

        $wh = $this->postJson('/api/v1/platform/resources/'.$resource->json('data.id').'/webhook')
            ->assertOk();
        $this->assertStringContainsString('/api/v1/platform/webhooks/deploy/', $wh->json('data.url'));

        $token = $wh->json('data.token');
        $this->postJson('/api/v1/platform/webhooks/deploy/'.$token)
            ->assertStatus(202);

        $this->postJson('/api/v1/platform/tokens', [
            'name' => 'ci',
            'abilities' => ['read', 'deploy'],
        ])->assertCreated()
            ->assertJsonStructure(['data' => ['plain_token']]);

        $this->putJson('/api/v1/platform/settings', [
            'default_proxy' => 'caddy',
            'api_enabled' => true,
        ])->assertOk()->assertJsonPath('data.default_proxy', 'caddy');
    }

    public function test_cafe_license_uses_site_type_matrix(): void
    {
        $this->seed(SiteBuilderSeeder::class);
        $package = WebinoPackage::query()->first();
        $this->assertNotNull($package);

        /** @var LicenseProvisionerService $svc */
        $svc = $this->app->make(LicenseProvisionerService::class);
        $license = $svc->createForProvision(
            'cafe-demo.webina.test',
            $package,
            ['site_type' => 'cafe'],
            null,
        );

        $meta = $license->meta ?? [];
        $this->assertSame('cafe', $meta['site_type'] ?? null);
        $this->assertContains('cafe', $meta['modules'] ?? []);
        $this->assertContains('commerce', $meta['modules'] ?? []);
        $this->assertNotContains('magazine', $meta['modules'] ?? []);
        $this->assertSame(SiteTypeProfiles::modulesFor('cafe'), $meta['module_matrix'] ?? null);
    }

    public function test_orchestrator_defaults_to_local_same_vps(): void
    {
        $this->seed(SiteBuilderSeeder::class);
        $package = WebinoPackage::query()->first();
        $provision = \Modules\SiteBuilder\Entities\WebinoSiteProvision::query()->create([
            'package_id' => $package->id,
            'slug' => 'no-server-site',
            'domain' => 'no-server.webina.test',
            'status' => \Modules\SiteBuilder\Entities\WebinoSiteProvision::STATUS_DRAFT,
            'wizard_payload' => ['site_type_slug' => 'cafe', 'site_name' => 'Cafe'],
        ]);

        $local = \Mockery::mock(\Modules\Platform\Services\LocalSameVpsProvisioner::class);
        $local->shouldReceive('provisionFromSiteBuilder')
            ->once()
            ->andReturnUsing(function ($p, $server) {
                $this->assertTrue((bool) $server->is_localhost);
                $p->update(['status' => \Modules\SiteBuilder\Entities\WebinoSiteProvision::STATUS_READY]);

                return new \Modules\Platform\Entities\PlatformResource([
                    'name' => $p->slug,
                    'type' => 'webino_dashboard',
                ]);
            });
        $this->app->instance(\Modules\Platform\Services\LocalSameVpsProvisioner::class, $local);

        /** @var SiteProvisionOrchestrator $orch */
        $orch = $this->app->make(SiteProvisionOrchestrator::class);
        $result = $orch->launch($provision);
        $this->assertSame(\Modules\SiteBuilder\Entities\WebinoSiteProvision::STATUS_READY, $result->status);
        $this->assertDatabaseHas('platform_servers', ['name' => 'localhost', 'is_localhost' => true]);
    }

    public function test_service_templates_list(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);
        $this->seed(\Modules\Platform\Database\Seeders\PlatformServiceTemplatesSeeder::class);
        $this->getJson('/api/v1/platform/services/templates')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }
}
