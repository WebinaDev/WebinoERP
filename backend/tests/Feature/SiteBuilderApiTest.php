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
}
