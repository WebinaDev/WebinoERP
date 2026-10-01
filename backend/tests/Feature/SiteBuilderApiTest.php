<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Queue\Connectors\ConnectorInterface;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Entities\CoreHostingSetting;
use Modules\Core\Entities\CoreLicense;
use Modules\Core\Entities\SystemModule;
use Modules\Core\Services\CoreLicenseResolver;
use Modules\Crm\Entities\CrmAccount;
use Modules\Platform\Entities\PlatformEnvironment;
use Modules\Platform\Entities\PlatformProject;
use Modules\Platform\Entities\PlatformResource;
use Modules\Platform\Entities\PlatformServer;
use Modules\Platform\Services\LocalSameVpsProvisioner;
use Modules\SiteBuilder\Database\Seeders\SiteBuilderSeeder;
use Modules\SiteBuilder\Entities\WebinoBusinessCategory;
use Modules\SiteBuilder\Entities\WebinoPackage;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;
use Modules\SiteBuilder\Jobs\ProvisionWebinoSiteJob;
use Modules\SiteBuilder\Jobs\UpdateWebinoSiteJob;
use Modules\SiteBuilder\Services\SiteProvisionOrchestrator;
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
            ->assertJsonStructure(['data' => ['license' => ['domain']]]);

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

        $account = CrmAccount::query()->create([
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

        Bus::fake();

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

        Bus::assertDispatched(
            UpdateWebinoSiteJob::class
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

        $this->mock(LocalSameVpsProvisioner::class, function ($mock) use ($provision) {
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

        $project = PlatformProject::query()->firstOrCreate(
            ['name' => 'power-cafe-test'],
            ['description' => 'test']
        );
        $env = PlatformEnvironment::query()->firstOrCreate(
            ['project_id' => $project->id, 'name' => 'production']
        );
        $server = PlatformServer::query()->firstOrCreate(
            ['name' => 'localhost'],
            [
                'ip' => '127.0.0.1',
                'port' => 22,
                'user' => 'root',
                'status' => 'ready',
                'is_localhost' => true,
            ]
        );
        PlatformResource::query()->create([
            'environment_id' => $env->id,
            'server_id' => $server->id,
            'type' => 'webino_dashboard',
            'name' => 'power-cafe',
            'status' => 'running',
            'fqdn' => $provision->domain,
            'provision_id' => $provision->id,
        ]);

        $this->mock(LocalSameVpsProvisioner::class, function ($mock) {
            $mock->shouldReceive('powerState')->andReturnUsing(function ($p) {
                $status = (string) (PlatformResource::query()
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
                PlatformResource::query()
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
            ->assertJsonPath('data.power_state', 'stopped')
            ->assertJsonPath('meta.compose.exit_code', 0)
            ->assertJsonPath('meta.power_state', 'stopped');

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

        Bus::fake();

        $this->postJson('/api/v1/site-builder/provisions/'.$provision->id.'/update', [
            'target' => 'migrate',
        ])->assertOk();
    }

    public function test_platform_webino_launch_queues_site_builder_job(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);
        Bus::fake();

        $package = WebinoPackage::query()->first();
        $server = PlatformServer::query()->firstOrCreate(
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

        Bus::assertDispatched(
            ProvisionWebinoSiteJob::class
        );
    }

    public function test_repair_db_and_bootstrap_preserve_compose_in_meta(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $package = WebinoPackage::query()->first();
        $provision = WebinoSiteProvision::query()->create([
            'package_id' => $package->id,
            'slug' => 'compose-cafe',
            'domain' => 'compose-cafe.webinaagency.ir',
            'status' => WebinoSiteProvision::STATUS_READY,
            'wizard_payload' => ['site_name' => 'Compose Cafe'],
            'provision_token' => 'tok-compose-cafe',
        ]);

        $this->mock(SiteProvisionOrchestrator::class, function ($mock) use ($provision) {
            $mock->shouldReceive('repairDatabase')->once()->andReturn([
                'exit_code' => 0,
                'stdout' => 'ok',
                'stderr' => '',
                'log' => 'repaired',
                'stages' => ['db_auth' => true],
            ]);
            $mock->shouldReceive('bootstrapSite')->once()->andReturn([
                'exit_code' => 0,
                'stdout' => 'bootstrapped',
                'stderr' => '',
                'log' => 'bootstrapped',
            ]);
            $mock->shouldReceive('powerState')->andReturn('running');
            $mock->shouldReceive('sslInfo')->andReturn([
                'ssl_status' => null,
                'expires_at' => null,
                'domain' => $provision->domain,
            ]);
            $mock->shouldReceive('stackDiagnostics')->andReturn([
                'project' => 'ws-compose-cafe',
                'containers' => [],
                'log' => '',
            ]);
        });

        $this->postJson('/api/v1/site-builder/provisions/'.$provision->id.'/repair-db')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.compose.exit_code', 0)
            ->assertJsonPath('meta.compose.log', 'repaired')
            ->assertJsonPath('data.id', $provision->id);

        $this->postJson('/api/v1/site-builder/provisions/'.$provision->id.'/bootstrap')
            ->assertOk()
            ->assertJsonPath('meta.compose.exit_code', 0)
            ->assertJsonPath('data.id', $provision->id);
    }

    public function test_light_control_does_not_pretend_diagnostics_failed(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $package = WebinoPackage::query()->first();
        $provision = WebinoSiteProvision::query()->create([
            'package_id' => $package->id,
            'slug' => 'light-cafe',
            'domain' => 'light-cafe.webinaagency.ir',
            'status' => WebinoSiteProvision::STATUS_READY,
            'wizard_payload' => ['site_name' => 'Light Cafe'],
            'provision_token' => 'tok-light-cafe',
        ]);

        $this->mock(LocalSameVpsProvisioner::class, function ($mock) {
            $mock->shouldReceive('powerState')->andReturn('running');
            $mock->shouldReceive('stackDiagnostics')->never();
            $mock->shouldReceive('sslInfo')->never();
        });

        $this->getJson('/api/v1/site-builder/provisions/'.$provision->id.'/control?light=1')
            ->assertOk()
            ->assertJsonPath('data.stack.diagnostics_ran', false)
            ->assertJsonPath('data.stack.checks.db_auth', 'not_run')
            ->assertJsonPath('data.stack.checks.backend_self', 'not_run')
            ->assertJsonPath('data.stack.checks.caddy_to_backend', 'not_run')
            ->assertJsonPath('data.stack.checks.on_webino_sites_frontend', 'not_run')
            ->assertJsonPath('data.stack.db_auth_ok', null);
    }

    public function test_full_control_gives_stack_diagnostics_the_client_budget(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $package = WebinoPackage::query()->first();
        $provision = WebinoSiteProvision::query()->create([
            'package_id' => $package->id,
            'slug' => 'full-cafe',
            'domain' => 'full-cafe.webinaagency.ir',
            'status' => WebinoSiteProvision::STATUS_READY,
            'wizard_payload' => ['site_name' => 'Full Cafe'],
            'provision_token' => 'tok-full-cafe',
        ]);

        $this->mock(LocalSameVpsProvisioner::class, function ($mock) use ($provision) {
            $mock->shouldReceive('powerState')->andReturn('running');
            $mock->shouldReceive('sslInfo')->andReturn([
                'ssl_status' => null,
                'expires_at' => null,
                'domain' => $provision->domain,
            ]);
            $mock->shouldReceive('stackDiagnostics')
                ->once()
                ->withArgs(function ($p, $budget) use ($provision) {
                    return $p->id === $provision->id
                        && is_numeric($budget)
                        && (float) $budget >= 40.0
                        && (float) $budget <= 50.5;
                })
                ->andReturn([
                    'project' => 'ws-full-cafe',
                    'containers' => [],
                    'on_webino_sites' => ['backend' => true, 'frontend' => true],
                    'db_auth_ok' => true,
                    'backend_self' => false,
                    'diagnostics_ran' => true,
                    'checks' => [
                        'db_auth' => 'ok',
                        'backend_self' => 'skipped',
                    ],
                    'log' => 'backend_self: skipped (budget)',
                ]);
        });

        $this->getJson('/api/v1/site-builder/provisions/'.$provision->id.'/control?light=0')
            ->assertOk()
            ->assertJsonPath('data.stack.diagnostics_ran', true)
            ->assertJsonPath('data.stack.checks.db_auth', 'ok')
            ->assertJsonPath('data.stack.checks.backend_self', 'skipped');
    }

    public function test_prepare_license_reuses_existing_domain_product_license(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $package = WebinoPackage::query()->where('sku', 'pkg-ecommerce-starter')->first()
            ?? WebinoPackage::query()->first();
        $this->assertNotNull($package);

        $domain = 'parisma.webinaagency.ir';
        $existing = CoreLicense::createForSchema([
            'license_key' => CoreLicenseResolver::internalKeyFor($domain, 'webinodashboard'),
            'project_name' => 'Parisma',
            'domain' => $domain,
            'product' => 'webinodashboard',
            'status' => 'active',
            'start_date' => now()->toDateString(),
        ]);

        $account = CrmAccount::query()->create([
            'name' => 'Parisma',
            'type' => 'customer',
        ]);

        $provision = WebinoSiteProvision::query()->create([
            'crm_account_id' => $account->id,
            'package_id' => $package->id,
            'slug' => 'parisma',
            'domain' => $domain,
            'status' => WebinoSiteProvision::STATUS_DRAFT,
            'wizard_payload' => [
                'site_name' => 'Parisma',
                'site_type_slug' => 'ecommerce',
            ],
            'provision_token' => 'tok-parisma',
        ]);

        $first = $this->postJson('/api/v1/site-builder/provisions/'.$provision->id.'/prepare-license');
        $first->assertOk()
            ->assertJsonPath('data.license.id', $existing->id)
            ->assertJsonPath('data.license.domain', $domain)
            ->assertJsonPath('data.status', 'draft');

        $this->assertSame(1, CoreLicense::query()->where('domain', $domain)->count());

        $this->postJson('/api/v1/site-builder/provisions/'.$provision->id.'/prepare-license')
            ->assertOk()
            ->assertJsonPath('data.license.id', $existing->id);

        $this->assertSame(1, CoreLicense::query()->where('domain', $domain)->count());
    }

    public function test_prepare_license_reports_revoked_wrong_product_and_customer_conflicts(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);
        $package = WebinoPackage::query()->first();
        $this->assertNotNull($package);

        $revokedDomain = 'revoked-shop.webinaagency.ir';
        $revoked = CoreLicense::createForSchema([
            'license_key' => CoreLicenseResolver::internalKeyFor($revokedDomain, 'webinodashboard'),
            'project_name' => 'Revoked',
            'domain' => $revokedDomain,
            'product' => 'webinodashboard',
            'status' => 'revoked',
            'start_date' => now()->toDateString(),
        ]);
        $revokedProvision = WebinoSiteProvision::query()->create([
            'package_id' => $package->id,
            'slug' => 'revoked-shop',
            'domain' => $revokedDomain,
            'status' => WebinoSiteProvision::STATUS_DRAFT,
            'wizard_payload' => ['site_name' => 'Revoked', 'site_type_slug' => 'ecommerce'],
        ]);
        $this->postJson('/api/v1/site-builder/provisions/'.$revokedProvision->id.'/prepare-license')
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'platform.license_revoked')
            ->assertJsonPath('message', 'platform.license_revoked');
        $this->assertNull($revokedProvision->fresh()->license_id);
        $this->assertSame($revoked->id, CoreLicense::query()->where('domain', $revokedDomain)->value('id'));

        $wpDomain = 'wp-shop.webinaagency.ir';
        CoreLicense::createForSchema([
            'license_key' => CoreLicenseResolver::internalKeyFor($wpDomain, 'wordpress'),
            'project_name' => 'WP',
            'domain' => $wpDomain,
            'product' => 'wordpress',
            'status' => 'active',
            'start_date' => now()->toDateString(),
        ]);
        $wpProvision = WebinoSiteProvision::query()->create([
            'package_id' => $package->id,
            'slug' => 'wp-shop',
            'domain' => $wpDomain,
            'status' => WebinoSiteProvision::STATUS_DRAFT,
            'wizard_payload' => ['site_name' => 'WP', 'site_type_slug' => 'ecommerce'],
        ]);
        $this->postJson('/api/v1/site-builder/provisions/'.$wpProvision->id.'/prepare-license')
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'platform.license_wrong_product');

        $sharedDomain = 'shared-shop.webinaagency.ir';
        $shared = CoreLicense::createForSchema([
            'license_key' => CoreLicenseResolver::internalKeyFor($sharedDomain, 'webinodashboard'),
            'project_name' => 'Shared',
            'domain' => $sharedDomain,
            'product' => 'webinodashboard',
            'status' => 'active',
            'start_date' => now()->toDateString(),
        ]);
        $owner = CrmAccount::query()->create(['name' => 'Owner', 'type' => 'customer']);
        $other = CrmAccount::query()->create(['name' => 'Other', 'type' => 'customer']);
        WebinoSiteProvision::query()->create([
            'crm_account_id' => $owner->id,
            'package_id' => $package->id,
            'license_id' => $shared->id,
            'slug' => 'shared-owner',
            'domain' => $sharedDomain,
            'status' => WebinoSiteProvision::STATUS_READY,
            'wizard_payload' => ['site_name' => 'Owner'],
        ]);
        $challenger = WebinoSiteProvision::query()->create([
            'crm_account_id' => $other->id,
            'package_id' => $package->id,
            'slug' => 'shared-other',
            'domain' => $sharedDomain,
            'status' => WebinoSiteProvision::STATUS_DRAFT,
            'wizard_payload' => ['site_name' => 'Other', 'site_type_slug' => 'ecommerce'],
        ]);
        $this->postJson('/api/v1/site-builder/provisions/'.$challenger->id.'/prepare-license')
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'platform.license_customer_conflict');

        $sameCustomer = WebinoSiteProvision::query()->create([
            'crm_account_id' => $owner->id,
            'package_id' => $package->id,
            'slug' => 'shared-owner-retry',
            'domain' => $sharedDomain,
            'status' => WebinoSiteProvision::STATUS_DRAFT,
            'wizard_payload' => ['site_name' => 'Owner retry', 'site_type_slug' => 'ecommerce'],
        ]);
        $this->postJson('/api/v1/site-builder/provisions/'.$sameCustomer->id.'/prepare-license')
            ->assertOk()
            ->assertJsonPath('data.license.id', $shared->id);
    }

    public function test_launch_queues_job_and_repeat_launch_does_not_dispatch_again(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);
        Bus::fake();

        $package = WebinoPackage::query()->first();
        $provision = WebinoSiteProvision::query()->create([
            'package_id' => $package->id,
            'slug' => 'queue-shop',
            'domain' => 'queue-shop.webinaagency.ir',
            'status' => WebinoSiteProvision::STATUS_DRAFT,
            'wizard_payload' => ['site_name' => 'Queue', 'site_type_slug' => 'ecommerce'],
        ]);

        $this->postJson('/api/v1/site-builder/provisions/'.$provision->id.'/launch')
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.progress.phase', 'queued')
            ->assertJsonPath('data.progress.percent', 5);

        Bus::assertDispatchedTimes(ProvisionWebinoSiteJob::class, 1);
        Bus::assertDispatched(ProvisionWebinoSiteJob::class, function (ProvisionWebinoSiteJob $job) use ($provision) {
            return $job->provisionId === $provision->id && $job->connection === 'redis';
        });
        Bus::assertNotDispatchedAfterResponse(ProvisionWebinoSiteJob::class);

        $this->postJson('/api/v1/site-builder/provisions/'.$provision->id.'/launch')
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');

        Bus::assertDispatchedTimes(ProvisionWebinoSiteJob::class, 1);
        $this->assertDatabaseHas('webino_site_provisions', [
            'id' => $provision->id,
            'status' => 'pending',
        ]);
    }

    public function test_launch_after_reused_license_sets_pending_and_queues_job(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);
        Bus::fake();

        $package = WebinoPackage::query()->where('sku', 'pkg-ecommerce-starter')->first()
            ?? WebinoPackage::query()->first();
        $this->assertNotNull($package);

        $domain = 'parisma.webinaagency.ir';
        $existing = CoreLicense::createForSchema([
            'license_key' => CoreLicenseResolver::internalKeyFor($domain, 'webinodashboard'),
            'project_name' => 'Parisma',
            'domain' => $domain,
            'product' => 'webinodashboard',
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'meta' => [
                'modules' => ['shop', 'cms'],
                'vertical' => 'ecommerce',
                'module_matrix' => ['shop' => true],
            ],
        ]);

        $account = CrmAccount::query()->create([
            'name' => 'Parisma',
            'type' => 'customer',
        ]);

        $provision = WebinoSiteProvision::query()->create([
            'crm_account_id' => $account->id,
            'package_id' => $package->id,
            'slug' => 'parisma',
            'domain' => $domain,
            'status' => WebinoSiteProvision::STATUS_DRAFT,
            'wizard_payload' => [
                'site_name' => 'Parisma',
                'site_type_slug' => 'ecommerce',
                'admin_name' => 'Admin',
                'admin_email' => 'admin@parisma.test',
            ],
            'provision_token' => 'tok-parisma',
        ]);

        $this->postJson('/api/v1/site-builder/provisions/'.$provision->id.'/prepare-license')
            ->assertOk()
            ->assertJsonPath('data.license.id', $existing->id)
            ->assertJsonPath('data.status', 'draft');

        $this->withoutExceptionHandling();
        $launch = $this->postJson('/api/v1/site-builder/provisions/'.$provision->id.'/launch');
        $launch->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.progress.phase', 'queued');

        Bus::assertDispatchedTimes(ProvisionWebinoSiteJob::class, 1);
        $this->assertDatabaseHas('webino_site_provisions', [
            'id' => $provision->id,
            'status' => 'pending',
            'license_id' => $existing->id,
        ]);
        $this->assertSame(1, CoreLicense::query()->where('domain', $domain)->count());

        Bus::assertDispatched(ProvisionWebinoSiteJob::class, function (ProvisionWebinoSiteJob $job) use ($provision) {
            return $job->provisionId === $provision->id && $job->connection === 'redis';
        });
        Bus::assertNotDispatchedAfterResponse(ProvisionWebinoSiteJob::class);
    }

    public function test_invalid_hosting_secret_is_rewritten_instead_of_throwing(): void
    {
        DB::table('core_hosting_settings')->insert([
            'platform_base_domain' => 'webinaagency.ir',
            'provision_webhook_secret' => 'not-a-valid-laravel-payload',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orchestrator = app(SiteProvisionOrchestrator::class);
        $method = new \ReflectionMethod($orchestrator, 'ensureLocalhostServer');
        $server = $method->invoke($orchestrator);

        $this->assertNotNull($server);
        $this->assertTrue((bool) $server->is_localhost);

        $settings = CoreHostingSetting::query()->first();
        $this->assertNotNull($settings);
        $secret = $settings->provision_webhook_secret;
        $this->assertIsString($secret);
        $this->assertNotSame('', $secret);
        $this->assertNotSame('not-a-valid-laravel-payload', $secret);
    }

    public function test_auth_limiters_stay_tighter_than_site_builder(): void
    {
        $request = Request::create('/api/v1/auth/login', 'POST');
        $auth = RateLimiter::limiter('auth-public')($request);
        $otp = RateLimiter::limiter('otp-send')($request);
        $siteBuilder = RateLimiter::limiter('site-builder')($request);

        $this->assertSame(20, $auth->maxAttempts);
        $this->assertSame(3, $otp->maxAttempts);
        $this->assertSame(600, $siteBuilder->maxAttempts);
    }

    public function test_status_polling_and_repeated_launch_are_not_throttled(): void
    {
        Cache::flush();
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);
        Bus::fake();

        $provision = $this->draftProvision('throttle-shop');

        for ($i = 0; $i < 65; $i++) {
            $this->getJson('/api/v1/site-builder/provisions/'.$provision->id.'/status')->assertOk();
        }

        $this->postJson('/api/v1/site-builder/provisions/'.$provision->id.'/prepare-license')
            ->assertOk();

        for ($i = 0; $i < 11; $i++) {
            $this->postJson('/api/v1/site-builder/provisions/'.$provision->id.'/launch')
                ->assertOk()
                ->assertJsonPath('data.status', 'pending');
        }

        Bus::assertDispatchedTimes(ProvisionWebinoSiteJob::class, 1);
    }

    public function test_launch_does_not_share_the_global_api_token_bucket(): void
    {
        Cache::flush();
        config(['api.rate_limit_per_minute' => 2]);

        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);
        Bus::fake();

        $provision = $this->draftProvision('token-bucket-shop');

        $this->getJson('/api/v1/site-builder/catalog')->assertOk();
        $this->getJson('/api/v1/site-builder/catalog')->assertOk();
        $this->getJson('/api/v1/site-builder/catalog')->assertStatus(429);

        $this->postJson('/api/v1/site-builder/provisions/'.$provision->id.'/launch')
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');

        Bus::assertDispatchedTimes(ProvisionWebinoSiteJob::class, 1);
    }

    public function test_launch_returns_pending_when_redis_is_down_and_database_queue_accepts_the_job(): void
    {
        $this->withoutExceptionHandling();
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);
        $provision = $this->draftProvision('redis-down-shop');
        $this->breakRedisQueue();

        $logged = [];
        Log::listen(function (object $event) use (&$logged): void {
            $logged[] = (string) $event->message;
        });

        $this->postJson('/api/v1/site-builder/provisions/'.$provision->id.'/launch')
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.progress.phase', 'queued');

        $this->assertDatabaseHas('webino_site_provisions', [
            'id' => $provision->id,
            'status' => 'pending',
        ]);
        $payload = (string) DB::table('jobs')->value('payload');
        $this->assertStringContainsString('ProvisionWebinoSiteJob', $payload);
        $this->assertTrue(
            collect($logged)->contains(fn (string $message) => str_contains($message, 'database fallback')),
            'Expected a logged error for the redis fallback. Got: '.implode(' | ', $logged),
        );
    }

    public function test_launch_returns_422_instead_of_500_when_every_queue_is_unavailable(): void
    {
        $this->withoutExceptionHandling();
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);
        $provision = $this->draftProvision('no-queue-shop');
        $this->breakRedisQueue();
        config(['queue.connections.database.connection' => 'broken_queue']);
        config(['database.connections.broken_queue' => [
            'driver' => 'sqlite',
            'database' => '/no/such/dir/webino-queue.sqlite',
            'prefix' => '',
        ]]);

        $this->postJson('/api/v1/site-builder/provisions/'.$provision->id.'/launch')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Unable to queue site provisioning.');

        $this->assertDatabaseHas('webino_site_provisions', [
            'id' => $provision->id,
            'status' => 'draft',
        ]);
        $this->assertSame(0, DB::table('jobs')->count());
    }

    private function draftProvision(string $slug): WebinoSiteProvision
    {
        $package = WebinoPackage::query()->first();
        $this->assertNotNull($package);

        return WebinoSiteProvision::query()->create([
            'package_id' => $package->id,
            'slug' => $slug,
            'domain' => $slug.'.webinaagency.ir',
            'status' => WebinoSiteProvision::STATUS_DRAFT,
            'wizard_payload' => ['site_name' => $slug, 'site_type_slug' => 'ecommerce'],
            'provision_token' => 'tok-'.$slug,
        ]);
    }

    private function breakRedisQueue(): void
    {
        app('queue')->addConnector('failing-redis', function () {
            return new class implements ConnectorInterface
            {
                public function connect(array $config)
                {
                    throw new \RuntimeException('redis connection refused');
                }
            };
        });

        config([
            'queue.default' => 'redis',
            'queue.connections.redis.driver' => 'failing-redis',
        ]);
    }
}
