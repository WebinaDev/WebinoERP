<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Entities\CoreLicense;
use Modules\Core\Entities\ModuleGitSource;
use Modules\Core\Entities\SystemModule;
use Modules\Marketplace\Entities\MarketplaceEntitlement;
use Modules\Marketplace\Entities\MarketplaceModule;
use Modules\Marketplace\Services\MarketplaceLicenseService;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class MarketplaceCrmParityTest extends TestCase
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

    public function test_modules_list_include_core_returns_crm_shape(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        MarketplaceModule::query()->create([
            'name' => 'Core Pack',
            'slug' => 'core-pack',
            'is_core' => true,
            'status' => 'active',
            'price' => 0,
        ]);
        MarketplaceModule::query()->create([
            'name' => 'Shop Addon',
            'slug' => 'shop-addon',
            'is_core' => false,
            'status' => 'active',
            'price' => 1000,
        ]);

        $withoutCore = $this->getJson('/api/v1/marketplace/modules?include_core=0')->assertOk();
        $withoutCore->assertJsonStructure(['data' => ['modules', 'categories']]);
        $slugs = collect($withoutCore->json('data.modules'))->pluck('slug')->all();
        $this->assertNotContains('core-pack', $slugs);
        $this->assertContains('shop-addon', $slugs);

        $withCore = $this->getJson('/api/v1/marketplace/modules?include_core=1')->assertOk();
        $withCore->assertJsonStructure(['data' => ['modules', 'categories']]);
        $slugsCore = collect($withCore->json('data.modules'))->pluck('slug')->all();
        $this->assertContains('core-pack', $slugsCore);
        $this->assertContains('shop-addon', $slugsCore);
    }

    public function test_category_auto_slug_from_name(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $res = $this->postJson('/api/v1/marketplace/categories', [
            'name' => 'Built In Tools',
        ])->assertCreated();

        $this->assertSame('built-in-tools', $res->json('data.slug'));

        $id = $res->json('data.id');
        $this->patchJson('/api/v1/marketplace/categories/'.$id, [
            'name' => 'Updated Category',
            'slug' => '',
        ])->assertOk()->assertJsonPath('data.slug', 'updated-category');

        $this->deleteJson('/api/v1/marketplace/categories/'.$id)->assertNoContent();
    }

    public function test_orders_index_returns_entitlements_key(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/marketplace/orders', [
            'total' => 1000,
            'status' => 'pending',
        ])->assertCreated();

        $this->getJson('/api/v1/marketplace/orders')
            ->assertOk()
            ->assertJsonStructure(['data' => ['orders', 'entitlements']]);
    }

    public function test_basalam_config_save_and_get(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/marketplace/basalam/oauth/config', [
            'client_id' => '9999',
            'client_secret' => 'super-secret-token',
            'redirect_uri' => 'https://example.test/api/basalam/oauth/callback',
            'scopes' => 'vendor.profile.read',
        ])->assertOk()
            ->assertJsonPath('data.ok', true)
            ->assertJsonPath('data.status.configured', true)
            ->assertJsonPath('data.status.client_id', '9999')
            ->assertJsonPath('data.status.client_secret', '***');

        $this->getJson('/api/v1/marketplace/basalam/oauth/config')
            ->assertOk()
            ->assertJsonPath('data.configured', true)
            ->assertJsonPath('data.client_id', '9999')
            ->assertJsonPath('data.client_secret', '***');

        $this->getJson('/api/v1/marketplace/basalam/oauth/status')
            ->assertOk()
            ->assertJsonPath('data.configured', true);
    }

    public function test_grant_from_order_creates_entitlement(): void
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
            'version' => '2.1.0',
        ]);

        $license = CoreLicense::query()->create([
            'license_key' => 'wb-entitlement-test-key-1234',
            'project_name' => 'Entitlement',
            'domain' => 'entitlement.example.test',
            'status' => 'active',
            'meta' => ['modules' => ['core']],
        ]);

        $site = WebinoSiteProvision::query()->create([
            'slug' => 'entitlement',
            'domain' => 'entitlement.example.test',
            'status' => WebinoSiteProvision::STATUS_READY,
            'license_id' => $license->id,
            'provision_token' => 'tok-entitlement',
        ]);

        $this->postJson('/api/v1/marketplace/orders/purchase', [
            'site_provision_id' => $site->id,
            'module_id' => $module->id,
            'mark_paid' => true,
        ])->assertCreated();

        $entitlement = MarketplaceEntitlement::query()
            ->where('domain', 'entitlement.example.test')
            ->where('module_id', $module->id)
            ->first();

        $this->assertNotNull($entitlement);
        $this->assertSame('owned', $entitlement->status);
        $this->assertSame('2.1.0', $entitlement->installed_version);
        $this->assertNotNull($entitlement->order_id);

        // Service-level upsert idempotency
        app(MarketplaceLicenseService::class)->upsertEntitlement(
            'entitlement.example.test',
            $module->id,
            $entitlement->order_id,
            '2.1.0'
        );
        $this->assertSame(1, MarketplaceEntitlement::query()
            ->where('domain', 'entitlement.example.test')
            ->where('module_id', $module->id)
            ->count());
    }

    public function test_gitea_settings_include_crm_fields_and_post_alias(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/marketplace/gitea/settings', [
            'gitea_base_url' => 'https://git.example.test',
            'gitea_org' => 'webina',
            'gitea_api_token' => 'tok-123',
            'gitea_ip_override' => '10.0.0.5',
            'gitea_ip_scheme' => 'https',
        ])->assertOk()
            ->assertJsonPath('data.gitea_base_url', 'https://git.example.test')
            ->assertJsonPath('data.gitea_org', 'webina')
            ->assertJsonPath('data.has_token', true)
            ->assertJsonPath('data.gitea_configured', true)
            ->assertJsonPath('data.gitea_ip_override', '10.0.0.5');

        $this->getJson('/api/v1/marketplace/gitea/settings')
            ->assertOk()
            ->assertJsonPath('data.gitea_org', 'webina')
            ->assertJsonPath('data.has_token', true);
    }
}
