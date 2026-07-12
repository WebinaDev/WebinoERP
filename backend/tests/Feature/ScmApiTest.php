<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Accounting\Entities\AccProduct;
use Modules\Accounting\Entities\AccWarehouse;
use Modules\Accounting\Entities\AccWarehouseDocument;
use Modules\Accounting\Entities\AccWarehouseStock;
use Modules\Core\Entities\SystemModule;
use Modules\Scm\Entities\ScmWarehouse;
use Modules\Scm\Entities\ScmWarehouseDocument;
use Modules\Scm\Entities\ScmWarehouseStock;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class ScmApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        SystemModule::create(['name' => 'SCM', 'slug' => 'scm', 'is_active' => true]);
    }

    public function test_warehouse_crud(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $create = $this->postJson('/api/v1/scm/warehouses', [
            'name' => 'Central Depot',
            'address' => 'Tehran',
            'is_default' => true,
            'is_active' => true,
        ]);
        $create->assertCreated()
            ->assertJsonPath('data.name', 'Central Depot');

        $warehouseId = $create->json('data.id');
        $this->assertDatabaseHas('scm_warehouses', [
            'id' => $warehouseId,
            'name' => 'Central Depot',
            'is_default' => true,
        ]);

        $this->getJson('/api/v1/scm/warehouses')
            ->assertOk()
            ->assertJsonPath('data.0.id', $warehouseId);

        $this->postJson("/api/v1/scm/warehouses/{$warehouseId}", [
            'name' => 'Updated Depot',
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.warehouse.name', 'Updated Depot');

        $this->assertDatabaseHas('scm_warehouses', [
            'id' => $warehouseId,
            'name' => 'Updated Depot',
            'is_active' => false,
        ]);

        $this->deleteJson("/api/v1/scm/warehouses/{$warehouseId}")
            ->assertNoContent();

        $this->assertDatabaseMissing('scm_warehouses', ['id' => $warehouseId]);
    }

    public function test_inbound_create_and_post_flow(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $product = AccProduct::create(['name' => 'Widget A']);
        $warehouse = ScmWarehouse::create(['name' => 'Main WH']);

        $create = $this->postJson('/api/v1/scm/inbound/create', [
            'warehouse_id' => $warehouse->id,
            'reference' => 'IN-001',
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ]);
        $create->assertCreated();

        $id = $create->json('data.id');
        $this->assertDatabaseHas('scm_warehouse_documents', [
            'id' => $id,
            'type' => 'inbound',
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
        ]);

        $this->postJson('/api/v1/scm/inbound/post', ['id' => $id])
            ->assertOk()
            ->assertJsonPath('data.posted', true);

        $this->assertDatabaseHas('scm_warehouse_documents', [
            'id' => $id,
            'status' => 'posted',
        ]);

        $this->assertDatabaseHas('scm_warehouse_stock', [
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => '2.0000',
        ]);
    }

    public function test_stock_query(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $product = AccProduct::create(['name' => 'Widget B']);
        $warehouse = ScmWarehouse::create(['name' => 'Stock WH']);
        ScmWarehouseStock::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 15,
        ]);

        $this->getJson('/api/v1/scm/stock?filter[warehouse_id]='.$warehouse->id)
            ->assertOk()
            ->assertJsonPath('data.0.warehouse_id', $warehouse->id)
            ->assertJsonPath('data.0.product_id', $product->id)
            ->assertJsonPath('data.0.quantity', '15.0000');

        $this->getJson("/api/v1/scm/stock/{$warehouse->id}/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.stock.quantity', '15.0000');
    }

    public function test_migrate_from_accounting_command(): void
    {
        $accWarehouse = AccWarehouse::create([
            'name' => 'Legacy WH',
            'address' => 'Isfahan',
            'is_default' => true,
            'is_active' => true,
        ]);
        $product = AccProduct::create(['name' => 'Legacy Product']);
        AccWarehouseDocument::create([
            'type' => 'inbound',
            'warehouse_id' => $accWarehouse->id,
            'status' => 'posted',
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
        ]);
        AccWarehouseStock::create([
            'warehouse_id' => $accWarehouse->id,
            'product_id' => $product->id,
            'quantity' => 5,
        ]);

        $this->artisan('scm:migrate-from-accounting')
            ->assertSuccessful();

        $scmWarehouse = ScmWarehouse::query()->where('name', 'Legacy WH')->first();
        $this->assertNotNull($scmWarehouse);
        $this->assertSame('Isfahan', $scmWarehouse->address);

        $this->assertDatabaseHas('scm_warehouse_documents', [
            'warehouse_id' => $scmWarehouse->id,
            'type' => 'inbound',
            'status' => 'posted',
        ]);

        $this->assertDatabaseHas('scm_warehouse_stock', [
            'warehouse_id' => $scmWarehouse->id,
            'product_id' => $product->id,
            'quantity' => '5.0000',
        ]);
    }

    public function test_list_smoke_endpoints(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/scm/warehouses')->assertOk();
        $this->getJson('/api/v1/scm/stock')->assertOk();
        $this->getJson('/api/v1/scm/outbound')->assertOk();
        $this->getJson('/api/v1/scm/audit')->assertOk();
    }
}
