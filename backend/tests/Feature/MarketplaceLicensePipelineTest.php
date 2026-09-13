<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Entities\CoreLicense;
use Modules\Core\Entities\ModuleGitSource;
use Modules\Core\Entities\SystemModule;
use Modules\Marketplace\Entities\MarketplaceModule;
use Modules\Marketplace\Services\MarketplaceLicenseService;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class MarketplaceLicensePipelineTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        SystemModule::create(['name' => 'Marketplace', 'slug' => 'marketplace', 'is_active' => true]);
        SystemModule::query()->firstOrCreate(['slug' => 'platform'], ['name' => 'Platform', 'is_active' => true]);
    }

    public function test_purchase_mark_paid_grants_license_meta_and_repos(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $source = ModuleGitSource::query()->create([
            'slug' => 'demo_paid',
            'clone_url' => 'https://github.com/WebinaDev/demo-paid.git',
            'auth_type' => 'pat',
        ]);

        $module = MarketplaceModule::query()->create([
            'name' => 'Demo Paid',
            'slug' => 'demo_paid',
            'distribution' => 'git',
            'price' => 50000,
            'requires_license' => true,
            'module_git_source_id' => $source->id,
            'status' => 'published',
        ]);

        $license = CoreLicense::query()->create([
            'license_key' => 'wb-pipeline-test-key-123456',
            'project_name' => 'Pipeline',
            'domain' => 'pipeline.example.test',
            'status' => 'active',
            'meta' => ['modules' => ['core', 'cms']],
        ]);

        $site = WebinoSiteProvision::query()->create([
            'slug' => 'pipeline',
            'domain' => 'pipeline.example.test',
            'status' => WebinoSiteProvision::STATUS_READY,
            'license_id' => $license->id,
            'provision_token' => 'tok-test',
        ]);

        $res = $this->postJson('/api/v1/marketplace/orders/purchase', [
            'site_provision_id' => $site->id,
            'module_id' => $module->id,
            'mark_paid' => true,
        ])->assertCreated();

        $this->assertSame('fulfilled', $res->json('data.order.status'));

        $license->refresh();
        $modules = $license->meta['modules'] ?? [];
        $this->assertContains('demo_paid', $modules);
        $repos = $license->meta['module_repos'] ?? [];
        $this->assertNotEmpty($repos);
        $this->assertTrue(collect($repos)->contains(fn ($r) => ($r['slug'] ?? '') === 'demo_paid'));
    }

    public function test_clone_url_requires_entitlement(): void
    {
        config(['app.webinocrm_license_hmac_secret' => 'test-secret']);

        ModuleGitSource::query()->create([
            'slug' => 'demo_paid',
            'clone_url' => 'https://github.com/WebinaDev/demo-paid.git',
            'auth_type' => 'pat',
        ]);

        $license = CoreLicense::query()->create([
            'license_key' => 'wb-clone-test-key-12345678',
            'project_name' => 'Clone',
            'domain' => 'clone.example.test',
            'status' => 'active',
            'meta' => ['modules' => ['core']],
        ]);

        $ts = time();
        $sig = hash_hmac('sha256', 'clone.example.test|'.$license->license_key.'|'.$ts, 'test-secret');

        $this->postJson('/api/webinocrm/v1/license/module-clone-url', [
            'domain' => 'clone.example.test',
            'license_key' => $license->license_key,
            'module_slug' => 'demo_paid',
            'ts' => $ts,
            'signature' => $sig,
        ])->assertForbidden()
            ->assertJsonPath('errors.code', 'NOT_LICENSED');
    }

    public function test_license_service_grant_module_to_site(): void
    {
        ModuleGitSource::query()->create([
            'slug' => 'cafe',
            'clone_url' => 'https://github.com/WebinaDev/webino-module-cafe.git',
            'auth_type' => 'pat',
        ]);

        $license = CoreLicense::query()->create([
            'license_key' => 'wb-grant-test-key-123456789',
            'project_name' => 'Grant',
            'domain' => 'grant.example.test',
            'status' => 'active',
            'meta' => ['modules' => ['core']],
        ]);

        $site = WebinoSiteProvision::query()->create([
            'slug' => 'grant',
            'domain' => 'grant.example.test',
            'status' => WebinoSiteProvision::STATUS_READY,
            'license_id' => $license->id,
        ]);

        $updated = app(MarketplaceLicenseService::class)->grantModuleToSite($site, 'cafe');
        $this->assertContains('cafe', $updated->meta['modules']);
    }
}
