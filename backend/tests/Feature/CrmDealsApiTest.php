<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Entities\SystemModule;
use Modules\Crm\Entities\CrmAccount;
use Modules\Crm\Entities\CrmDeal;
use Modules\Crm\Entities\CrmPipeline;
use Modules\Crm\Entities\CrmStage;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class CrmDealsApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        SystemModule::create(['name' => 'CRM', 'slug' => 'crm', 'is_active' => true]);
    }

    public function test_deal_move_and_kanban_and_sources(): void
    {
        $user = $this->actingAsRole('sales_consultant');
        Sanctum::actingAs($user);

        $pipeline = CrmPipeline::create(['name' => 'Sales', 'is_active' => true, 'created_by' => $user->id]);
        $stage1 = CrmStage::create(['pipeline_id' => $pipeline->id, 'name' => 'New', 'sort_order' => 1, 'color' => '#000', 'probability' => 10]);
        $stage2 = CrmStage::create(['pipeline_id' => $pipeline->id, 'name' => 'Won', 'sort_order' => 2, 'color' => '#0f0', 'probability' => 100, 'is_closed' => true, 'is_won' => true]);
        $account = CrmAccount::create(['name' => 'ACME', 'type' => 'customer']);
        $deal = CrmDeal::create([
            'name' => 'Big deal',
            'account_id' => $account->id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stage1->id,
            'created_by' => $user->id,
        ]);

        $this->patchJson('/api/v1/crm/deals/'.$deal->id.'/move', ['stage_id' => $stage2->id])->assertOk();
        $this->getJson('/api/v1/crm/pipelines/'.$pipeline->id.'/kanban')->assertOk();

        $this->postJson('/api/v1/crm/sources', ['name' => 'Website', 'color' => '#00f'])->assertCreated();
        $this->getJson('/api/v1/crm/sources')->assertOk();
    }

    public function test_list_smoke_endpoints(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/crm/leads')->assertOk();
        $this->getJson('/api/v1/crm/pipelines')->assertOk();
        $this->getJson('/api/v1/crm/consultations')->assertOk();
    }
}
