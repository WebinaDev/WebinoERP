<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Accounting\Entities\AccProduct;
use Modules\Core\Entities\SystemModule;
use Modules\Crm\Entities\CrmCompany;
use Modules\Org\Entities\OrgBranch;
use Modules\Org\Entities\OrgTransfer;
use Modules\Workflow\Entities\WfDefinition;
use Modules\Workflow\Entities\WfInstance;
use Modules\Bi\Entities\BiSubscription;
use Modules\FieldService\Entities\FsInstallBase;
use Modules\FieldService\Entities\FsServiceOrder;
use Modules\Qm\Entities\QmInspectionPlan;
use Modules\Qm\Entities\QmLotBatch;
use Modules\Qm\Entities\QmInspection;
use Modules\Assets\Entities\AstAsset;
use Modules\Assets\Entities\AstMaintenancePlan;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class ErpMaturityModulesTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->seedLicensedModules();
    }

    public function test_priority1_org_multi_company_and_branches(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        // 1. Create company
        $resCo = $this->postJson('/api/v1/org/companies', [
            'name' => 'Dez Avan Medical Co',
            'legal_name' => 'شرکت توسعه پزشکی دز آوان',
            'national_id' => '10103847561',
            'economic_code' => '4115897456',
            'currency_code' => 'IRR',
            'is_default' => true,
        ]);
        $resCo->assertCreated();
        $companyId = $resCo->json('data.id');

        // 2. Create branch
        $resBr = $this->postJson('/api/v1/org/branches', [
            'company_id' => $companyId,
            'code' => 'TBZ-01',
            'name' => 'شعبه مرکزی تبریز',
            'type' => 'regional',
            'phone' => '04133334444',
        ]);
        $resBr->assertCreated();
        $branchId = $resBr->json('data.id');

        // 3. Switch context
        $resSwitch = $this->postJson('/api/v1/org/context/switch', [
            'company_id' => $companyId,
            'branch_id' => $branchId,
        ]);
        $resSwitch->assertOk()
            ->assertJsonPath('data.company.id', $companyId)
            ->assertJsonPath('data.branch.id', $branchId);

        // 4. Test document numbering sequence
        $resNum = $this->postJson('/api/v1/org/sequences/next', [
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'document_type' => 'sales_invoice',
            'prefix' => 'INV-TBZ-',
        ]);
        $resNum->assertOk()
            ->assertJsonPath('data.number', 'INV-TBZ-00001');

        // 5. Consolidated report
        $resConsolidated = $this->getJson('/api/v1/org/reports/consolidated');
        $resConsolidated->assertOk()
            ->assertJsonStructure(['data' => ['holding_summary', 'companies']]);
    }

    public function test_priority2_workflow_approval_engine(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        // 1. Create workflow definition
        $resDef = $this->postJson('/api/v1/workflow/definitions', [
            'name' => 'تاییدیه فاکتورهای فروش بالای ۵۰ میلیون ریال',
            'module_slug' => 'sales',
            'entity_type' => 'sales_invoice',
            'conditions' => ['min_amount' => 50000000],
            'steps' => [
                ['order' => 1, 'type' => 'role', 'target' => 'system_manager', 'sla_hours' => 24],
            ],
            'is_active' => true,
        ]);
        $resDef->assertCreated();

        // 2. Initiate workflow
        $resInit = $this->postJson('/api/v1/workflow/initiate', [
            'module_slug' => 'sales',
            'entity_type' => 'sales_invoice',
            'entity_id' => 101,
            'title' => 'فاکتور فروش شماره INV-900',
            'amount' => 75000000,
            'context' => ['customer_name' => 'بیمارستان امام رضا'],
        ]);
        $resInit->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        $instanceId = $resInit->json('data.id');

        // 3. Check inbox & decide
        $resInbox = $this->getJson('/api/v1/workflow/inbox');
        $resInbox->assertOk();
        $approvalId = $resInbox->json('data.0.id');

        $resDecide = $this->postJson("/api/v1/workflow/approvals/{$approvalId}/decide", [
            'decision' => 'approve',
            'comment' => 'بررسی شد و تایید گردید.',
        ]);
        $resDecide->assertOk()
            ->assertJsonPath('data.status', 'approved');
    }

    public function test_priority3_bi_and_dashboards(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        // 1. Query KPI summary
        $resKpis = $this->getJson('/api/v1/bi/kpis');
        $resKpis->assertOk()
            ->assertJsonStructure(['data' => ['sales_margin', 'inventory_turnover', 'ar_collection', 'lead_conversion', 'project_profitability', 'support_sla']]);

        // 2. Role executive dashboard
        $resCeo = $this->getJson('/api/v1/bi/dashboards/ceo');
        $resCeo->assertOk()
            ->assertJsonPath('data.role', 'ceo');

        // 3. Export CSV
        $resExport = $this->getJson('/api/v1/bi/export?format=csv');
        $resExport->assertOk()
            ->assertJsonPath('data.format', 'csv');

        // 4. Create subscription
        $resSub = $this->postJson('/api/v1/bi/subscriptions', [
            'name' => 'گزارش هفتگی مدیرعامل',
            'report_type' => 'executive_summary',
            'frequency' => 'weekly',
            'format' => 'json',
            'recipients' => [$user->id],
        ]);
        $resSub->assertCreated();
    }

    public function test_priority4_field_service_lifecycle(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $product = AccProduct::create(['name' => 'دستگاه ساکشن جراحی']);

        // 1. Create install base
        $resIb = $this->postJson('/api/v1/field-service/install-bases', [
            'serial_number' => 'DEV-SN-998822',
            'device_model' => 'VAC-PRO-2026',
            'product_id' => $product->id,
            'install_date' => now()->subMonths(6)->toDateString(),
            'warranty_start' => now()->subMonths(6)->toDateString(),
            'warranty_end' => now()->addMonths(18)->toDateString(),
            'installation_address' => 'تهران، مرکز پزشکی هسته‌ای',
        ]);
        $resIb->assertCreated();
        $ibId = $resIb->json('data.id');

        // 2. Register technician
        $resTech = $this->postJson('/api/v1/field-service/technicians', [
            'user_id' => $user->id,
            'skills' => ['medical_devices', 'calibration'],
            'zone' => 'منطقه مرکز',
        ]);
        $resTech->assertCreated();
        $techId = $resTech->json('data.id');

        // 3. Create service order
        $resOrder = $this->postJson('/api/v1/field-service/orders', [
            'install_base_id' => $ibId,
            'priority' => 'high',
            'problem_description' => 'افت فشار پمپ و نیاز به تعویض فیلتر هپا',
        ]);
        $resOrder->assertCreated();
        $orderId = $resOrder->json('data.id');

        // 4. Dispatch
        $this->postJson("/api/v1/field-service/orders/{$orderId}/dispatch", [
            'technician_id' => $techId,
        ])->assertOk()->assertJsonPath('data.status', 'dispatched');

        // 5. Add spare part (warranty covered)
        $part = AccProduct::create(['name' => 'فیلتر هپا ضدباکتری']);
        $this->postJson("/api/v1/field-service/orders/{$orderId}/spare-parts", [
            'product_id' => $part->id,
            'quantity' => 1,
            'unit_cost' => 4500000,
        ])->assertCreated()->assertJsonPath('data.is_warranty_covered', true);

        // 6. Complete
        $resComplete = $this->postJson("/api/v1/field-service/orders/{$orderId}/complete", [
            'resolution_notes' => 'فیلتر هپا تعویض و تست کالیبراسیون فشار تایید شد.',
            'labor_cost' => 1200000,
        ]);
        $resComplete->assertOk()
            ->assertJsonPath('data.order.status', 'completed');
    }

    public function test_priority5_qm_quality_management_and_gate(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $product = AccProduct::create(['name' => 'سرم فیزیولوژی ۵۰۰ میلی‌لیتر']);

        // 1. Create inspection plan
        $resPlan = $this->postJson('/api/v1/qm/plans', [
            'title' => 'طرح بازرسی ورودی اقلام تزریقی',
            'inspection_type' => 'incoming',
            'product_id' => $product->id,
            'criteria' => [
                ['parameter' => 'pH Level', 'spec_min' => 6.8, 'spec_max' => 7.4, 'target' => 7.0, 'unit' => 'pH'],
            ],
        ]);
        $resPlan->assertCreated();
        $planId = $resPlan->json('data.id');

        // 2. Quarantine lot batch
        $resLot = $this->postJson('/api/v1/qm/lots', [
            'lot_number' => 'LOT-MED-2026-X1',
            'product_id' => $product->id,
            'quantity' => 5000,
            'status' => 'quarantined',
        ]);
        $resLot->assertCreated()
            ->assertJsonPath('data.status', 'quarantined');
        $lotId = $resLot->json('data.id');

        // 3. Inspection passing releases quarantine
        $resInsp = $this->postJson('/api/v1/qm/inspections', [
            'plan_id' => $planId,
            'product_id' => $product->id,
            'lot_id' => $lotId,
            'inspection_type' => 'incoming',
            'sample_size' => 10,
            'verdict' => 'pass',
            'remarks' => 'نتایج آزمون آزمایشگاهی با برگه آنالیز مطابقت دارد.',
        ]);
        $resInsp->assertCreated();

        $this->assertDatabaseHas('qm_lot_batches', [
            'id' => $lotId,
            'status' => 'released',
        ]);
    }

    public function test_priority6_assets_and_depreciation(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        // 1. Create fixed asset
        $resAsset = $this->postJson('/api/v1/assets/items', [
            'asset_code' => 'AST-CNC-2026-01',
            'name' => 'دستگاه تراش CNC ۵ محوره هاس',
            'category' => 'machinery',
            'purchase_cost' => 1200000000,
            'salvage_value' => 120000000,
            'useful_life_months' => 60,
            'depreciation_method' => 'straight_line',
        ]);
        $resAsset->assertCreated();
        $assetId = $resAsset->json('data.id');

        // 2. Run monthly depreciation
        $resDepr = $this->postJson('/api/v1/assets/depreciation-runs', [
            'period_date' => now()->toDateString(),
        ]);
        $resDepr->assertCreated()
            ->assertJsonPath('data.status', 'posted');

        $this->assertDatabaseHas('ast_assets', [
            'id' => $assetId,
            'accumulated_depreciation' => 18000000.0, // (1200M - 120M) / 60 = 18M
        ]);

        // 3. Preventive maintenance plan
        $resPm = $this->postJson('/api/v1/assets/maintenance-plans', [
            'asset_id' => $assetId,
            'title' => 'روغن‌کاری و کالیبراسیون هفتگی محورها',
            'frequency' => 'weekly',
            'interval_days' => 7,
            'next_due_date' => now()->addDays(7)->toDateString(),
        ]);
        $resPm->assertCreated();
    }
}
