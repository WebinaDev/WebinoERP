<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Entities\SystemModule;
use Modules\SiteBuilder\Database\Seeders\SiteBuilderSeeder;
use Modules\SiteBuilder\Entities\WebinoBusinessCategory;
use Modules\SiteBuilder\Entities\WebinoPackage;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class SiteBuilderApiTest extends TestCase
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
        $this->seed(SiteBuilderSeeder::class);
    }

    public function test_catalog_and_provision_crud(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/site-builder/catalog')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'site_types');

        $package = WebinoPackage::query()->first();
        $this->assertNotNull($package);

        $create = $this->postJson('/api/v1/site-builder/provisions', [
            'package_id' => $package->id,
            'wizard_payload' => [
                'site_name' => 'Test Cafe',
                'currency' => 'IRR',
            ],
        ]);
        $create->assertCreated();
        $id = $create->json('data.id');
        $this->assertNotNull($id);

        $this->patchJson("/api/v1/site-builder/provisions/{$id}", [
            'wizard_payload' => ['site_name' => 'Cafe Updated'],
        ])->assertOk();

        $this->getJson("/api/v1/site-builder/provisions/{$id}/status")->assertOk();

        $this->postJson('/api/v1/site-builder/provisions/'.$id.'/prepare-license')
            ->assertOk()
            ->assertJsonStructure(['data' => ['license' => ['license_key']]]);

        $this->postJson('/api/v1/site-builder/categories', [
            'slug' => 'test_cat',
            'name_fa' => 'تست',
            'name_en' => 'Test',
            'sort_order' => 99,
        ])->assertCreated()->assertJsonPath('data.slug', 'test_cat');
    }

    public function test_empty_site_name_gets_unique_slug_and_resume_package_exists(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $resume = WebinoPackage::query()->where('sku', 'pkg-resume-starter')->first();
        $this->assertNotNull($resume);

        $account = \Modules\Crm\Entities\CrmAccount::query()->create([
            'name' => 'مبین حبیبی',
            'type' => 'individual',
        ]);

        $first = $this->postJson('/api/v1/site-builder/provisions', [
            'crm_account_id' => $account->id,
            'wizard_payload' => ['site_name' => '', 'site_type_slug' => 'resume'],
        ]);
        $first->assertCreated();
        $slug1 = $first->json('data.slug');
        $this->assertNotSame('', $slug1);
        $this->assertDoesNotMatchRegularExpression('/^\./', (string) $first->json('data.domain'));

        $second = $this->postJson('/api/v1/site-builder/provisions', [
            'crm_account_id' => $account->id,
            'package_id' => $resume->id,
            'wizard_payload' => ['site_name' => 'رزومه مبین', 'site_type_slug' => 'resume'],
        ]);
        $second->assertCreated();
        $this->assertNotSame('', $second->json('data.slug'));
        $this->assertNotSame($slug1, $second->json('data.slug'));
    }

    public function test_license_meta_includes_business_fields(): void
    {
        $this->seed(SiteBuilderSeeder::class);
        $category = WebinoBusinessCategory::query()->where('slug', 'site_types')->first();
        $this->assertNotNull($category);

        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $package = WebinoPackage::query()->first();
        $provision = WebinoSiteProvision::query()->create([
            'package_id' => $package->id,
            'slug' => 'test-shop',
            'domain' => 'test-shop.webina.local',
            'status' => WebinoSiteProvision::STATUS_DRAFT,
            'wizard_payload' => ['site_name' => 'Shop'],
        ]);

        $this->assertDatabaseHas('webino_site_provisions', ['slug' => 'test-shop']);
        $this->assertSame('draft', $provision->status);
    }

    public function test_cancel_sets_cancelled_status_and_progress(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $package = WebinoPackage::query()->first();
        $provision = WebinoSiteProvision::query()->create([
            'package_id' => $package->id,
            'slug' => 'cancel-me',
            'domain' => 'cancel-me.webina.local',
            'status' => WebinoSiteProvision::STATUS_PENDING,
            'wizard_payload' => ['site_name' => 'Cancel Me'],
            'progress' => [
                'phase' => 'queued',
                'percent' => 5,
                'label_fa' => 'در صف اجرا',
                'label_en' => 'Queued',
                'eta_seconds' => 180,
                'images_cached' => true,
                'updated_at' => now()->toIso8601String(),
            ],
        ]);

        $this->postJson('/api/v1/site-builder/provisions/'.$provision->id.'/cancel')
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.progress.phase', 'cancelled');

        $this->assertDatabaseHas('webino_site_provisions', [
            'id' => $provision->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_status_returns_progress_shape(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $package = WebinoPackage::query()->first();
        $provision = WebinoSiteProvision::query()->create([
            'package_id' => $package->id,
            'slug' => 'progress-shop',
            'domain' => 'progress-shop.webina.local',
            'status' => WebinoSiteProvision::STATUS_PROVISIONING,
            'wizard_payload' => ['site_name' => 'Progress'],
            'progress' => [
                'phase' => 'compose_up',
                'percent' => 65,
                'label_fa' => 'بالا آوردن کانتینرها',
                'label_en' => 'Starting containers',
                'eta_seconds' => 70,
                'images_cached' => true,
                'updated_at' => now()->toIso8601String(),
            ],
        ]);

        $this->getJson('/api/v1/site-builder/provisions/'.$provision->id.'/status')
            ->assertOk()
            ->assertJsonPath('data.progress.phase', 'compose_up')
            ->assertJsonPath('data.progress.percent', 65)
            ->assertJsonStructure(['data' => ['progress' => ['phase', 'percent', 'label_fa', 'eta_seconds']]]);
    }

    public function test_ssl_pending_allows_control_patch_and_update_queue(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        \Illuminate\Support\Facades\Bus::fake();

        $package = WebinoPackage::query()->first();
        $provision = WebinoSiteProvision::query()->create([
            'package_id' => $package->id,
            'slug' => 'ssl-cafe',
            'domain' => 'ssl-cafe.webinaagency.ir',
            'status' => WebinoSiteProvision::STATUS_SSL_PENDING,
            'wizard_payload' => ['site_name' => 'SSL Cafe', 'channel' => 'beta'],
            'provision_token' => 'tok-ssl-cafe',
        ]);

        $this->patchJson('/api/v1/site-builder/provisions/'.$provision->id, [
            'site_name' => 'SSL Cafe Renamed',
            'logo_url' => 'https://cdn.example/logo.png',
        ])
            ->assertOk()
            ->assertJsonPath('data.wizard_payload.site_name', 'SSL Cafe Renamed');

        $this->postJson('/api/v1/site-builder/provisions/'.$provision->id.'/update', [
            'target' => 'frontend',
        ])
            ->assertOk()
            ->assertJsonPath('data.wizard_payload.update.status', 'queued')
            ->assertJsonPath('data.wizard_payload.update.target', 'frontend');

        \Illuminate\Support\Facades\Bus::assertDispatched(
            \Modules\SiteBuilder\Jobs\UpdateWebinoSiteJob::class
        );
    }

    public function test_ssl_renew_route_promotes_ssl_pending_when_ok(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $package = WebinoPackage::query()->first();
        $provision = WebinoSiteProvision::query()->create([
            'package_id' => $package->id,
            'slug' => 'renew-cafe',
            'domain' => 'renew-cafe.webinaagency.ir',
            'status' => WebinoSiteProvision::STATUS_SSL_PENDING,
            'wizard_payload' => ['site_name' => 'Renew Cafe'],
            'provision_token' => 'tok-renew-cafe',
        ]);

        $this->mock(\Modules\Platform\Services\LocalSameVpsProvisioner::class, function ($mock) use ($provision) {
            $mock->shouldReceive('renewSsl')
                ->once()
                ->withArgs(fn ($p, $force) => $p->id === $provision->id && $force === false)
                ->andReturn([
                    'ok' => true,
                    'ssl_status' => 'active',
                    'expires_at' => '2027-01-01T00:00:00+00:00',
                    'forced' => false,
                    'log' => 'caddy reload requested',
                ]);
            $mock->shouldReceive('sslInfo')->andReturn([
                'ssl_status' => 'active',
                'expires_at' => '2027-01-01T00:00:00+00:00',
                'domain' => $provision->domain,
            ]);
            $mock->shouldReceive('powerState')->andReturn('running');
            $mock->shouldReceive('stackDiagnostics')->andReturn([
                'project' => 'ws-renew-cafe',
                'containers' => [],
                'on_webino_sites' => ['backend' => true, 'frontend' => true],
                'caddy_to_backend' => true,
                'frontend_to_backend' => true,
                'db_auth_ok' => true,
                'log' => '',
            ]);
        });

        $this->postJson('/api/v1/site-builder/provisions/'.$provision->id.'/ssl/renew', [
            'force' => false,
        ])
            ->assertOk()
            ->assertJsonPath('meta.ssl.ok', true)
            ->assertJsonPath('meta.ssl.ssl_status', 'active');

        $this->assertDatabaseHas('webino_site_provisions', [
            'id' => $provision->id,
            'status' => WebinoSiteProvision::STATUS_READY,
        ]);

        $this->getJson('/api/v1/site-builder/provisions/'.$provision->id.'/control')
            ->assertOk()
            ->assertJsonPath('data.ssl.ssl_status', 'active');
    }

    public function test_control_exposes_power_state_and_logo_can_clear(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $package = WebinoPackage::query()->first();
        $provision = WebinoSiteProvision::query()->create([
            'package_id' => $package->id,
            'slug' => 'power-cafe',
            'domain' => 'power-cafe.webinaagency.ir',
            'status' => WebinoSiteProvision::STATUS_READY,
            'wizard_payload' => ['site_name' => 'Power Cafe', 'logo_url' => 'https://cdn.example/a.png'],
            'provision_token' => 'tok-power-cafe',
        ]);

        $project = \Modules\Platform\Entities\PlatformProject::query()->firstOrCreate(
            ['name' => 'power-cafe-test'],
            ['description' => 'test']
        );
        $env = \Modules\Platform\Entities\PlatformEnvironment::query()->firstOrCreate(
            ['project_id' => $project->id, 'name' => 'production']
        );
        \Modules\Platform\Entities\PlatformResource::query()->create([
            'environment_id' => $env->id,
            'type' => 'webino_dashboard',
            'name' => 'power-cafe',
            'status' => 'running',
            'fqdn' => $provision->domain,
            'provision_id' => $provision->id,
        ]);

        $this->mock(\Modules\Platform\Services\LocalSameVpsProvisioner::class, function ($mock) {
            $mock->shouldReceive('powerState')->andReturnUsing(function ($p) {
                $status = (string) (\Modules\Platform\Entities\PlatformResource::query()
                    ->where('provision_id', $p->id)
                    ->value('status') ?? '');

                return match ($status) {
                    'running' => 'running',
                    'stopped', 'destroyed' => 'stopped',
                    default => 'unknown',
                };
            });
            $mock->shouldReceive('sslInfo')->andReturn([
                'ssl_status' => null,
                'expires_at' => null,
                'domain' => 'power-cafe.webinaagency.ir',
            ]);
            $mock->shouldReceive('stackDiagnostics')->andReturn([
                'project' => 'ws-power-cafe',
                'containers' => [],
                'on_webino_sites' => ['backend' => false, 'frontend' => false],
                'caddy_to_backend' => false,
                'frontend_to_backend' => false,
                'db_auth_ok' => false,
                'log' => '',
            ]);
            $mock->shouldReceive('stop')->once()->andReturnUsing(function ($p) {
                \Modules\Platform\Entities\PlatformResource::query()
                    ->where('provision_id', $p->id)
                    ->update(['status' => 'stopped']);

                return [
                    'exit_code' => 0,
                    'stdout' => 'stopped',
                    'stderr' => '',
                ];
            });
            $mock->shouldReceive('callTenantApi')->andReturn([]);
        });

        $this->getJson('/api/v1/site-builder/provisions/'.$provision->id.'/control')
            ->assertOk()
            ->assertJsonPath('data.power_state', 'running');

        $this->postJson('/api/v1/site-builder/provisions/'.$provision->id.'/stop')
            ->assertOk()
            ->assertJsonPath('power_state', 'stopped');

        $this->assertDatabaseHas('platform_resources', [
            'provision_id' => $provision->id,
            'status' => 'stopped',
        ]);

        $this->patchJson('/api/v1/site-builder/provisions/'.$provision->id, [
            'logo_url' => '',
        ])->assertOk();

        $fresh = $provision->fresh();
        $this->assertArrayHasKey('logo_url', $fresh->wizard_payload);
        $this->assertNull($fresh->wizard_payload['logo_url']);
    }

    public function test_stable_channel_rejected_and_failed_status_is_controllable(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $package = WebinoPackage::query()->first();
        $provision = WebinoSiteProvision::query()->create([
            'package_id' => $package->id,
            'slug' => 'failed-cafe',
            'domain' => 'failed-cafe.webinaagency.ir',
            'status' => WebinoSiteProvision::STATUS_FAILED,
            'wizard_payload' => ['site_name' => 'Failed Cafe'],
            'provision_token' => 'tok-failed-cafe',
        ]);

        $this->postJson('/api/v1/site-builder/provisions/'.$provision->id.'/channel', [
            'channel' => 'stable',
        ])->assertStatus(503);

        \Illuminate\Support\Facades\Bus::fake();

        $this->postJson('/api/v1/site-builder/provisions/'.$provision->id.'/update', [
            'target' => 'migrate',
        ])->assertOk();
    }

    public function test_platform_webino_launch_queues_site_builder_job(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);
        \Illuminate\Support\Facades\Bus::fake();

        $package = WebinoPackage::query()->first();
        $server = \Modules\Platform\Entities\PlatformServer::query()->firstOrCreate(
            ['name' => 'localhost'],
            [
                'ip' => '127.0.0.1',
                'port' => 22,
                'user' => 'root',
                'status' => 'ready',
                'is_localhost' => true,
            ]
        );
        $provision = WebinoSiteProvision::query()->create([
            'package_id' => $package->id,
            'slug' => 'queue-cafe',
            'domain' => 'queue-cafe.webinaagency.ir',
            'status' => WebinoSiteProvision::STATUS_DRAFT,
            'wizard_payload' => ['site_name' => 'Queue Cafe'],
            'provision_token' => 'tok-queue-cafe',
        ]);

        $this->postJson('/api/v1/platform/webino/launch', [
            'provision_id' => $provision->id,
            'server_id' => $server->id,
        ])->assertStatus(202);

        \Illuminate\Support\Facades\Bus::assertDispatched(
            \Modules\SiteBuilder\Jobs\ProvisionWebinoSiteJob::class
        );
    }
}
