<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Entities\SystemModule;
use Modules\Marketplace\Entities\MarketplaceModule;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class MarketplaceApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        SystemModule::create(['name' => 'Marketplace', 'slug' => 'marketplace', 'is_active' => true]);
    }

    public function test_module_and_release_flow(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $create = $this->postJson('/api/v1/marketplace/modules', [
            'name' => 'HRM Module',
            'slug' => 'hrm-module',
            'price' => 1000000,
        ]);
        $create->assertCreated();
        $moduleId = $create->json('data.id');

        $this->postJson('/api/v1/marketplace/modules/'.$moduleId.'/repo', [
            'repo_url' => 'https://git.example.com/hrm.git',
        ])->assertCreated();

        $release = $this->postJson('/api/v1/marketplace/modules/'.$moduleId.'/releases', [
            'version' => '1.0.0',
            'changelog' => 'Initial',
        ]);
        $release->assertCreated();
        $releaseId = $release->json('data.id');

        $this->postJson('/api/v1/marketplace/releases/'.$releaseId.'/publish')->assertOk();
        $this->assertSame('published', MarketplaceModule::find($moduleId)?->status);
    }

    public function test_orders_crud_flow(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $create = $this->postJson('/api/v1/marketplace/orders', [
            'total' => 1500000,
            'status' => 'pending',
        ]);
        $create->assertCreated();
        $orderId = $create->json('data.id');
        $this->assertNotEmpty($create->json('data.order_number'));

        $this->getJson('/api/v1/marketplace/orders/'.$orderId)->assertOk()
            ->assertJsonPath('data.total', '1500000.00');

        $this->patchJson('/api/v1/marketplace/orders/'.$orderId, [
            'status' => 'paid',
            'total' => 1600000,
        ])->assertOk()->assertJsonPath('data.status', 'paid');

        $this->deleteJson('/api/v1/marketplace/orders/'.$orderId)->assertNoContent();
        $this->getJson('/api/v1/marketplace/orders/'.$orderId)->assertNotFound();
    }

    public function test_client_cannot_manage_orders(): void
    {
        $user = $this->actingAsRole('client');
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/marketplace/orders', ['total' => 100])->assertForbidden();
    }

    public function test_catalog_smoke_endpoints(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/marketplace/products')->assertOk();
        $this->getJson('/api/v1/marketplace/categories')->assertOk();
        $this->getJson('/api/v1/marketplace/gitea/settings')->assertOk();
    }
}
