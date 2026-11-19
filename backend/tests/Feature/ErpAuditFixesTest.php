<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Modules\Accounting\Entities\AccPerson;
use Modules\Accounting\Entities\AccProduct;
use Modules\Docs\Entities\DocsContract;
use Modules\Docs\Entities\DocsSignatureEnvelope;
use Modules\Docs\Services\ContractLifecycleService;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

/** Regression tests for the fixes made while auditing the arena ERP modules. */
class ErpAuditFixesTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->seedLicensedModules();
    }

    public function test_company_creator_becomes_active_member(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $id = $this->postJson('/api/v1/org/companies', ['name' => 'Audit Co', 'currency_code' => 'IRR'])
            ->assertCreated()->json('data.id');

        $this->assertTrue(DB::table('crm_company_users')->where(['user_id' => $user->id, 'company_id' => $id, 'is_active' => true])->exists());
        $this->assertTrue(DB::table('authz_memberships')->where(['user_id' => $user->id, 'company_id' => $id, 'status' => 'active'])->exists());

        $this->postJson('/api/v1/org/context/switch', ['company_id' => $id])->assertOk();
    }

    public function test_purchase_request_decision_and_rfq_award_flow(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);
        $product = AccProduct::create(['name' => 'Paper A4']);
        $vendor = AccPerson::query()->create(['name' => 'Vendor A', 'type' => 'vendor', 'mobile' => '09120000001']);
        $other = AccPerson::query()->create(['name' => 'Vendor B', 'type' => 'vendor', 'mobile' => '09120000002']);

        $prId = $this->postJson('/api/v1/procurement/requests', [
            'items' => [['product_id' => $product->id, 'quantity' => 10, 'estimated_price' => 1000]],
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/procurement/requests/{$prId}/decide", ['decision' => 'approve'])
            ->assertOk()->assertJsonPath('data.status', 'approved');
        $this->postJson("/api/v1/procurement/requests/{$prId}/decide", ['decision' => 'reject'])->assertStatus(422);

        $rfqId = $this->postJson('/api/v1/procurement/rfqs', ['title' => 'Paper', 'pr_id' => $prId])
            ->assertCreated()->json('data.id');

        $this->postJson("/api/v1/procurement/rfqs/{$rfqId}/bids", ['vendor_id' => $vendor->id, 'amount' => 9000, 'delivery_days' => 3])
            ->assertOk()->assertJsonPath('data.status', 'evaluating');
        // Re-bidding replaces the vendor's previous bid.
        $this->postJson("/api/v1/procurement/rfqs/{$rfqId}/bids", ['vendor_id' => $vendor->id, 'amount' => 8500])
            ->assertOk()->assertJsonCount(1, 'data.bids');

        $this->postJson("/api/v1/procurement/rfqs/{$rfqId}/award", ['vendor_id' => $other->id])->assertStatus(422);
        $this->postJson("/api/v1/procurement/rfqs/{$rfqId}/award", ['vendor_id' => $vendor->id])
            ->assertOk()->assertJsonPath('data.status', 'awarded');
    }

    public function test_esign_otp_is_locked_after_five_wrong_attempts(): void
    {
        $user = $this->actingAsRole('system_manager');
        $contract = DocsContract::create(['title' => 'NDA', 'status' => 'active', 'created_by' => $user->id]);
        $envelope = DocsSignatureEnvelope::query()->create([
            'contract_id' => $contract->id,
            'envelope_token' => str_repeat('a', 40),
            'signer_name' => 'Signer',
            'signer_mobile' => '09120000003',
            'otp_code_hash' => password_hash('123456', PASSWORD_BCRYPT),
            'otp_expires_at' => now()->addMinutes(10),
            'status' => 'pending',
        ]);
        $svc = app(ContractLifecycleService::class);

        for ($i = 0; $i < 5; $i++) {
            try {
                $svc->signEnvelope($envelope, '000000', '127.0.0.1', 'test');
                $this->fail('wrong OTP accepted');
            } catch (\RuntimeException) {
            }
        }

        $this->expectException(\RuntimeException::class);
        // Even the correct code is refused once the envelope is locked.
        $svc->signEnvelope($envelope, '123456', '127.0.0.1', 'test');
    }

    public function test_bi_kpis_do_not_invent_numbers_without_data(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $kpis = app(\Modules\Bi\Services\BiDataMartService::class)->getKpiSummary();
        $this->assertEquals(0, $kpis['sales_margin']['total_sales']);
        $this->assertNull($kpis['sales_margin']['margin_percent']);
        $this->assertNull($kpis['lead_conversion']['conversion_rate']);
        $this->assertNull($kpis['support_sla']['compliance_rate']);
        $this->assertNull($kpis['inventory_turnover']['turnover_ratio']);
    }
}
