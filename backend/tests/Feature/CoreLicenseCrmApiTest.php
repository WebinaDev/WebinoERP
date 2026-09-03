<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Entities\CoreLicense;
use Modules\Core\Entities\SystemModule;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class CoreLicenseCrmApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        SystemModule::query()->firstOrCreate(
            ['slug' => 'core'],
            ['name' => 'Core', 'is_active' => true]
        );
    }

    public function test_create_license_auto_key_and_domain_normalization(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $res = $this->postJson('/api/v1/core/licenses', [
            'project_name' => 'Demo Shop',
            'domain' => 'https://WWW.Demo-Shop.Example.com/path',
            'site_type' => 'ecommerce',
            'status' => 'active',
        ])->assertCreated();

        $key = $res->json('data.license_key');
        $this->assertNotEmpty($key);
        $this->assertStringStartsWith('wb-', $key);
        $this->assertSame('demo-shop.example.com', $res->json('data.domain'));
        $this->assertSame('Demo Shop', $res->json('data.project_name'));
        $this->assertSame('ecommerce', $res->json('data.meta.site_type'));
        $this->assertIsArray($res->json('data.meta.modules'));

        $this->postJson('/api/v1/core/licenses', [
            'project_name' => 'Dup',
            'domain' => 'demo-shop.example.com',
        ])->assertStatus(422);

        $this->assertDatabaseHas('core_licenses', [
            'domain' => 'demo-shop.example.com',
            'license_key' => $key,
        ]);
    }

    public function test_renew_accepts_custom_expiry(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $lic = CoreLicense::query()->create([
            'license_key' => 'wb-testkey123456789012345',
            'project_name' => 'X',
            'domain' => 'renew.test',
            'status' => 'active',
            'start_date' => now()->toDateString(),
        ]);

        $this->postJson('/api/v1/core/licenses/'.$lic->id.'/renew', [
            'expires_at' => '2030-06-01',
        ])->assertOk()->assertJsonPath('data.expires_at', function ($v) {
            return str_starts_with((string) $v, '2030-06-01');
        });
    }
}
