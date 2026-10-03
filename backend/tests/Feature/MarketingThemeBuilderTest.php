<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Database\Seeders\RolesAndPermissionsSeeder;
use Modules\Core\Entities\SystemModule;
use Tests\TestCase;

class MarketingThemeBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SystemModule::query()->firstOrCreate(
            ['slug' => 'marketing'],
            ['name' => 'Marketing', 'is_active' => true]
        );
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('system_manager');
        $this->actingAs($admin, 'sanctum');
    }

    public function test_condition_resolution_default_and_library(): void
    {
        $default = $this->postJson('/api/v1/theme-builder/templates', [
            'kind' => 'header',
            'title' => 'هدر اصلی',
            'document' => ['version' => 1, 'sections' => [['id' => 'sec_default', 'columns' => []]]],
        ])->assertCreated()->json('data');
        $this->assertTrue($default['is_default']);
        $this->postJson('/api/v1/theme-builder/templates/'.$default['id'].'/publish')->assertOk();

        $shop = $this->postJson('/api/v1/theme-builder/templates', [
            'kind' => 'header',
            'title' => 'هدر فروشگاه',
            'is_default' => false,
            'priority' => 10,
            'conditions' => ['include' => [['type' => 'url', 'value' => '/shop']]],
            'document' => ['version' => 1, 'sections' => [['id' => 'sec_shop', 'columns' => []]]],
        ])->assertCreated()->json('data');
        $this->assertFalse($shop['is_default']);
        $this->postJson('/api/v1/theme-builder/templates/'.$shop['id'].'/publish')->assertOk();

        $draftOnly = $this->postJson('/api/v1/theme-builder/templates', [
            'kind' => 'header',
            'title' => 'هدر پیش‌نویس',
            'priority' => 50,
            'conditions' => ['include' => [['type' => 'entire_site']]],
            'document' => ['version' => 1, 'sections' => [['id' => 'sec_draft', 'columns' => []]]],
        ])->assertCreated()->json('data');
        $this->assertFalse($draftOnly['has_published']);

        $this->getJson('/api/v1/public/builder/resolve?kind=header&path=/shop')
            ->assertOk()
            ->assertJsonPath('data.document.sections.0.id', 'sec_shop')
            ->assertJsonPath('data.is_default', false);

        $this->getJson('/api/v1/public/builder/resolve?kind=header&path=/')
            ->assertOk()
            ->assertJsonPath('data.document.sections.0.id', 'sec_default')
            ->assertJsonPath('data.is_default', true);

        $this->getJson('/api/v1/public/builder/templates/header')
            ->assertOk()
            ->assertJsonPath('data.document.sections.0.id', 'sec_default');

        $this->getJson('/api/v1/public/builder/resolve?kind=404&not_found=1')->assertNotFound();

        $this->postJson('/api/v1/theme-builder/templates/'.$shop['id'].'/default')->assertOk()
            ->assertJsonPath('data.is_default', true);
        $this->getJson('/api/v1/theme-builder/templates/'.$default['id'])
            ->assertOk()
            ->assertJsonPath('data.is_default', false);

        $this->postJson('/api/v1/theme-builder/templates/'.$default['id'].'/default')->assertOk();

        $applied = $this->postJson('/api/v1/theme-builder/library/apply', [
            'preset' => 'ishop-404',
        ])->assertCreated()->json('data');
        $this->assertSame('not_found', $applied['kind']);
        $this->assertTrue($applied['is_default']);
        $this->assertFalse($applied['has_published']);

        $this->postJson('/api/v1/theme-builder/library/apply', ['preset' => 'ishop-kit'])
            ->assertCreated()
            ->assertJsonStructure(['data' => ['templates']]);

        $this->postJson('/api/v1/theme-builder/library/apply', ['preset' => 'beauty-kit'])
            ->assertCreated();

        $this->postJson('/api/v1/theme-builder/library/apply', ['preset' => 'webina-kit'])
            ->assertCreated();

        $this->getJson('/api/v1/theme-builder')
            ->assertOk()
            ->assertJsonFragment(['kind' => 'single_product', 'label' => 'محصول تکی'])
            ->assertJsonFragment(['kind' => 'not_found', 'label' => 'صفحه ۴۰۴']);
    }

    public function test_global_settings_publish_and_tokens(): void
    {
        $this->getJson('/api/v1/public/builder/globals')->assertNotFound();

        $this->putJson('/api/v1/builder/globals', [
            'settings' => [
                'colors' => ['primary' => '#112233'],
                'cssVariables' => "--wb-extra: 4px;\nbody{color:red}",
            ],
        ])->assertOk()
            ->assertJsonPath('data.settings.colors.primary', '#112233')
            ->assertJsonPath('data.settings.colors.secondary', '#0C2D63')
            ->assertJsonPath('data.has_published', false);

        $this->getJson('/api/v1/public/builder/globals')->assertNotFound();

        $published = $this->postJson('/api/v1/builder/globals/publish')->assertOk()->json('data.settings');
        $this->assertSame('#112233', $published['colors']['primary']);
        $this->assertStringContainsString('--wb-extra: 4px;', $published['cssVariables']);
        $this->assertStringNotContainsString('body{', $published['cssVariables']);

        $this->getJson('/api/v1/public/builder/globals')
            ->assertOk()
            ->assertJsonPath('data.settings.colors.primary', '#112233');

        $this->putJson('/api/v1/builder/globals', [
            'settings' => ['colors' => ['primary' => '#abcdef']],
        ])->assertOk();
        $this->getJson('/api/v1/public/builder/globals')
            ->assertJsonPath('data.settings.colors.primary', '#112233');
    }

    public function test_builder_pages_keep_dashboard_document_shape(): void
    {
        $created = $this->postJson('/api/v1/builder/pages', [
            'title' => 'خانه وبینا',
            'slug' => 'home',
            'document' => ['version' => 1, 'sections' => [['id' => 'sec_home', 'columns' => []]]],
        ])->assertCreated()->json('data');

        $this->assertSame('home', $created['slug']);
        $this->assertSame('draft', $created['status']);
        $this->assertSame('sec_home', $created['document']['sections'][0]['id']);

        $this->postJson('/api/v1/builder/pages/'.$created['id'].'/publish')->assertOk()
            ->assertJsonPath('data.status', 'published');

        $this->getJson('/api/v1/public/builder/pages/home')
            ->assertOk()
            ->assertJsonPath('data.document.sections.0.id', 'sec_home');
    }
}
