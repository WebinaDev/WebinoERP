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
        SystemModule::query()->create(['name' => 'Site Builder', 'slug' => 'site_builder', 'is_active' => true]);
        $this->seed(SiteBuilderSeeder::class);
    }

    public function test_catalog_and_provision_crud(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/site-builder/catalog')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'retail');

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

        $this->postJson("/api/v1/site-builder/provisions/{$id}/prepare-license")
            ->assertOk()
            ->assertJsonStructure(['data' => ['license' => ['license_key']]]);
    }

    public function test_license_meta_includes_business_fields(): void
    {
        $this->seed(SiteBuilderSeeder::class);
        $category = WebinoBusinessCategory::query()->where('slug', 'retail')->first();
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
}
