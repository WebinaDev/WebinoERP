<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Accounting\Entities\AccProduct;
use Modules\Core\Entities\SystemModule;
use Modules\Mfg\Entities\MfgBom;
use Modules\Mfg\Entities\MfgWorkOrder;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class MfgApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        SystemModule::create(['name' => 'MFG', 'slug' => 'mfg', 'is_active' => true]);
    }

    public function test_bom_crud_with_lines(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $finished = AccProduct::create(['name' => 'Finished Good']);
        $component = AccProduct::create(['name' => 'Component A']);

        $res = $this->postJson('/api/v1/mfg/boms', [
            'product_id' => $finished->id,
            'version' => '1.0',
            'status' => 'active',
            'lines' => [
                ['component_product_id' => $component->id, 'quantity' => 2, 'scrap_percent' => 5],
            ],
        ]);
        $res->assertCreated();
        $bomId = $res->json('data.id');

        $this->getJson("/api/v1/mfg/boms/{$bomId}")
            ->assertOk()
            ->assertJsonPath('data.lines.0.component_product_id', $component->id);

        $this->putJson("/api/v1/mfg/boms/{$bomId}", ['notes' => 'Updated'])
            ->assertOk()
            ->assertJsonPath('data.notes', 'Updated');
    }

    public function test_work_order_lifecycle(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $product = AccProduct::create(['name' => 'Widget']);
        $component = AccProduct::create(['name' => 'Part']);
        $bom = MfgBom::create(['product_id' => $product->id, 'status' => 'active']);
        $bom->lines()->create(['component_product_id' => $component->id, 'quantity' => 1]);

        $create = $this->postJson('/api/v1/mfg/work-orders', [
            'bom_id' => $bom->id,
            'product_id' => $product->id,
            'qty_planned' => 10,
            'operations' => [['name' => 'Assembly', 'sequence' => 1]],
        ]);
        $create->assertCreated();
        $woId = $create->json('data.id');

        $this->postJson("/api/v1/mfg/work-orders/{$woId}/release")->assertOk()
            ->assertJsonPath('data.status', 'released');

        $this->postJson("/api/v1/mfg/work-orders/{$woId}/start")->assertOk()
            ->assertJsonPath('data.status', 'in_progress');

        $this->postJson("/api/v1/mfg/work-orders/{$woId}/complete", ['qty_produced' => 10])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');
    }

    public function test_quality_inspection_complete_pass_fail(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $product = AccProduct::create(['name' => 'QC Product']);
        $wo = MfgWorkOrder::create([
            'product_id' => $product->id,
            'qty_planned' => 1,
            'status' => 'in_progress',
        ]);

        $res = $this->postJson('/api/v1/mfg/inspections', [
            'work_order_id' => $wo->id,
            'type' => 'final',
            'check_items' => [
                ['criterion' => 'Weight', 'measured_value' => '100', 'spec_min' => 90, 'spec_max' => 110],
            ],
        ]);
        $res->assertCreated();
        $inspId = $res->json('data.id');

        $this->postJson("/api/v1/mfg/inspections/{$inspId}/complete")
            ->assertOk()
            ->assertJsonPath('data.result', 'pass');
    }

    public function test_mrp_returns_shortages(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $product = AccProduct::create(['name' => 'MRP Product']);
        $component = AccProduct::create(['name' => 'MRP Part']);
        $bom = MfgBom::create(['product_id' => $product->id, 'status' => 'active']);
        $bom->lines()->create(['component_product_id' => $component->id, 'quantity' => 5]);

        MfgWorkOrder::create([
            'bom_id' => $bom->id,
            'product_id' => $product->id,
            'qty_planned' => 2,
            'status' => 'released',
        ]);

        $this->getJson('/api/v1/mfg/planning/mrp?horizon_days=30')
            ->assertOk()
            ->assertJsonStructure(['data' => ['requirements', 'shortages', 'open_work_orders']]);
    }

    public function test_overview_and_forbidden_without_module(): void
    {
        SystemModule::query()->where('slug', 'mfg')->update(['is_active' => false]);

        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/mfg/overview')->assertStatus(403);
    }
}
