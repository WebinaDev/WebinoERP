<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Accounting\Entities\AccProduct;
use Modules\Accounting\Entities\AccWarehouse;
use Modules\Accounting\Entities\AccWarehouseDocument;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class WebinocrmV1ApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_warehouses_support_pagination_search_and_legacy_envelope(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        AccWarehouse::create(['name' => 'Main', 'code' => 'WH-1', 'location' => 'Tehran']);
        AccWarehouse::create(['name' => 'Secondary', 'code' => 'WH-2', 'location' => 'Tabriz']);

        $this->getJson('/api/webinocrm/v1/warehouses?per_page=1&page=1')
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/webinocrm/v1/warehouses?search=Tabriz')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.name', 'Secondary');

        $this->getJson('/api/webinocrm/v1/warehouses?legacy=1')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['items', 'total']]);
    }

    public function test_warehouse_crud_with_legacy_fields(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $create = $this->postJson('/api/webinocrm/v1/warehouses/create', [
            'name' => 'Legacy WH',
            'code' => 'L-1',
            'description' => 'Test warehouse',
            'location' => 'Isfahan',
        ]);
        $create->assertCreated();
        $id = $create->json('data.id');

        $this->postJson('/api/webinocrm/v1/warehouses/update', [
            'id' => $id,
            'description' => 'Updated',
        ])->assertOk();

        $row = AccWarehouse::query()->findOrFail($id);
        $this->assertSame('L-1', $row->code);
        $this->assertSame('Isfahan', $row->location);
        $this->assertSame('Isfahan', $row->address);

        $this->postJson('/api/webinocrm/v1/warehouses/delete', ['id' => $id])
            ->assertOk();
    }

    public function test_warehouse_routes_smoke(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $warehouse = AccWarehouse::create(['name' => 'WH']);
        $product = AccProduct::create(['name' => 'Item']);

        $routes = [
            ['GET', '/api/webinocrm/v1/warehouses'],
            ['GET', '/api/webinocrm/v1/products'],
            ['GET', '/api/webinocrm/v1/warehouse/stock'],
            ['GET', "/api/webinocrm/v1/warehouse/stock/{$warehouse->id}/{$product->id}"],
            ['GET', '/api/webinocrm/v1/warehouse/outbound'],
            ['GET', '/api/webinocrm/v1/warehouse/inbound'],
            ['GET', '/api/webinocrm/v1/warehouse/audit'],
        ];

        foreach ($routes as [$method, $uri]) {
            $this->json($method, $uri)->assertOk();
        }

        $outbound = AccWarehouseDocument::create([
            'type' => 'outbound',
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
            'items' => [],
        ]);
        $inbound = AccWarehouseDocument::create([
            'type' => 'inbound',
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
            'items' => [],
        ]);
        $audit = AccWarehouseDocument::create([
            'type' => 'audit',
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
            'items' => [],
        ]);

        $this->getJson("/api/webinocrm/v1/warehouse/outbound/{$outbound->id}")->assertOk();
        $this->getJson("/api/webinocrm/v1/warehouse/inbound/{$inbound->id}")->assertOk();
        $this->getJson("/api/webinocrm/v1/warehouse/audit/{$audit->id}")->assertOk();

        $this->postJson('/api/webinocrm/v1/warehouse/outbound/create', [
            'warehouse_id' => $warehouse->id,
        ])->assertCreated();

        $this->postJson('/api/webinocrm/v1/warehouse/inbound/create', [
            'warehouse_id' => $warehouse->id,
        ])->assertCreated();

        $this->postJson('/api/webinocrm/v1/warehouse/audit/create', [
            'warehouse_id' => $warehouse->id,
        ])->assertCreated();
    }

    public function test_license_check_requires_valid_signature(): void
    {
        $this->postJson('/api/webinocrm/v1/license/check', [
            'domain' => 'example.test',
            'license_key' => 'missing',
        ])->assertForbidden();
    }
}
