<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Database\Seeders\RolesAndPermissionsSeeder as R;
use Modules\Crm\Entities\CrmAccount;
use Modules\Crm\Events\Customer\ContractSigned;
use Modules\Crm\Services\CrmOutboundMessenger;
use Modules\Sales\Entities\SalesInvoice;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class Customer360Phase5Test extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->seedLicensedModules();
    }

    private function invoice(CrmAccount $a, float $total, string $status, string $date): SalesInvoice
    {
        return SalesInvoice::query()->create(['number' => 'INV-'.uniqid(), 'customer_name' => $a->name, 'customer_id' => $a->id, 'total' => $total,
            'subtotal' => $total, 'discount' => 0, 'status' => $status, 'issue_date' => $date]);
    }

    public function test_duplicates_list_per_account_dismiss_and_permissions(): void
    {
        $a = CrmAccount::query()->create(['name' => 'Acme Co', 'type' => 'customer', 'mobile' => '09121234567', 'national_id' => '1234567890']);
        $b = CrmAccount::query()->create(['name' => 'ACME', 'type' => 'customer', 'mobile' => '+989121234567', 'national_id' => '1234567890']);
        $c = CrmAccount::query()->create(['name' => 'Other', 'type' => 'customer', 'email' => 'x@o.ir']);
        $d = CrmAccount::query()->create(['name' => 'Other Ltd', 'type' => 'customer', 'email' => 'X@o.ir']);
        $this->invoice($b, 100, 'paid', now()->toDateString());

        Sanctum::actingAs($this->actingAsRole(R::ROLE_SYSTEM_MANAGER));
        $r = $this->getJson('/api/v1/crm/customers/duplicates')->assertOk()->assertJsonPath('meta.can.merge', true);
        $this->assertSame("{$a->id}-{$b->id}", $r->json('data.0.key'));
        $this->assertSame(100, $r->json('data.0.score'));
        $this->assertContains('national_id', $r->json('data.0.reasons'));
        $this->assertSame($b->id, $r->json('data.0.suggested_target_id'));
        $this->assertSame(1, $r->json('data.0.b.invoices_count'));
        $this->assertCount(2, $r->json('data'));
        $this->getJson("/api/v1/crm/customers/{$c->id}/hub/duplicates")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.reasons', ['email', 'name']);

        $this->postJson('/api/v1/crm/customers/duplicates/dismiss', ['a_id' => $b->id, 'b_id' => $a->id])->assertNoContent();
        $this->getJson('/api/v1/crm/customers/duplicates')->assertOk()->assertJsonCount(1, 'data');

        Sanctum::actingAs($this->actingAsRole(R::ROLE_CLIENT));
        $this->getJson('/api/v1/crm/customers/duplicates')->assertForbidden();
        $this->postJson('/api/v1/crm/customers/duplicates/dismiss', ['a_id' => $c->id, 'b_id' => $d->id])->assertForbidden();
    }

    public function test_export_template_and_import_with_dry_run_and_upsert(): void
    {
        $a = CrmAccount::query()->create(['name' => 'Exp Co', 'type' => 'customer', 'mobile' => '09120000001', 'national_id' => '111']);
        CrmAccount::query()->create(['name' => 'Gone', 'type' => 'customer', 'merged_into_id' => $a->id]);
        DB::table('crm_contacts')->insert(['account_id' => $a->id, 'first_name' => 'Ali', 'last_name' => 'R', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('crm_account_addresses')->insert(['account_id' => $a->id, 'title' => 'HQ', 'city' => 'Tehran', 'created_at' => now(), 'updated_at' => now()]);
        Sanctum::actingAs($this->actingAsRole(R::ROLE_SYSTEM_MANAGER));

        $csv = $this->get('/api/v1/crm/customers/export')->assertOk()->assertHeader('Content-Disposition')->getContent();
        $this->assertStringContainsString('Exp Co', $csv);
        $this->assertStringNotContainsString('Gone', $csv);
        $this->assertStringContainsString('Ali', $this->get('/api/v1/crm/customers/export?entity=contacts')->getContent());
        $this->assertStringContainsString('HQ', $this->get('/api/v1/crm/customers/export?entity=addresses')->getContent());
        $this->assertStringStartsWith('PK', $this->get('/api/v1/crm/customers/export?format=xlsx')->getContent());
        $this->assertStringContainsString('national_id', $this->get('/api/v1/crm/customers/import/template')->getContent());

        $file = fn () => UploadedFile::fake()->createWithContent('c.csv', "name,national_id,mobile,email\nExp Co Renamed,111,,\nNew Co,,09129999999,\n,,,bad\n");
        $this->post('/api/v1/crm/customers/import', ['file' => $file(), 'entity' => 'customers', 'dry_run' => 1], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('data.total', 3)->assertJsonPath('data.created', 1)->assertJsonPath('data.updated', 1)->assertJsonPath('data.skipped', 1);
        $this->assertSame('Exp Co', $a->fresh()->name);
        $this->post('/api/v1/crm/customers/import', ['file' => $file(), 'entity' => 'customers'], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.created', 1);
        $this->assertSame('Exp Co Renamed', $a->fresh()->name);
        $this->assertDatabaseHas('crm_accounts', ['name' => 'New Co', 'mobile' => '09129999999']);
        $contacts = UploadedFile::fake()->createWithContent('k.csv', "account_id,first_name,mobile\n{$a->id},Sara,0912\n");
        $this->post('/api/v1/crm/customers/import', ['file' => $contacts, 'entity' => 'contacts'], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.created', 1);
        $this->assertDatabaseHas('crm_contacts', ['account_id' => $a->id, 'first_name' => 'Sara']);

        Sanctum::actingAs($this->actingAsRole(R::ROLE_SALES_CONSULTANT));
        $this->get('/api/v1/crm/customers/export', ['Accept' => 'application/json'])->assertForbidden();
    }

    public function test_bi_reports_and_money_masking(): void
    {
        $a = CrmAccount::query()->create(['name' => 'Big', 'type' => 'customer', 'segment' => 'vip']);
        $b = CrmAccount::query()->create(['name' => 'Small', 'type' => 'customer']);
        $this->invoice($a, 50_000_000, 'paid', now()->subMonths(2)->toDateString());
        $this->invoice($a, 20_000_000, 'paid', now()->subMonth()->toDateString());
        $this->invoice($b, 1_000_000, 'paid', now()->subMonths(2)->toDateString());
        $this->invoice($b, 3_000_000, 'issued', now()->subDays(75)->toDateString());
        Sanctum::actingAs($this->actingAsRole(R::ROLE_SYSTEM_MANAGER));
        $base = '/api/v1/crm/customers/reports/';
        $this->getJson($base.'ltv')->assertOk()->assertJsonPath('data.customers', 2)->assertJsonPath('data.total_revenue', 71000000);
        $this->getJson($base.'debt')->assertOk()->assertJsonPath('data.total', 3000000)->assertJsonPath('data.buckets.d31_60', 3000000)->assertJsonPath('data.top_debtors.0.id', $b->id);
        $this->getJson($base.'top?limit=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $a->id)->assertJsonPath('data.0.invoices', 2);
        $this->getJson($base.'segments')->assertOk()->assertJsonPath('data.0.segment', 'vip');
        $cohort = $this->getJson($base.'cohorts')->assertOk()->json('data.0');
        $this->assertSame(2, $cohort['size']);
        $this->assertSame(50.0, (float) $cohort['months'][1]);
        $this->getJson($base.'churn')->assertOk()->assertJsonPath('data.active', 2)->assertJsonCount(12, 'data.by_month');
        $this->getJson($base.'nope')->assertNotFound();

        $user = User::factory()->create(['is_active' => true]);
        $user->givePermissionTo('crm.hub.bi.view');
        Sanctum::actingAs($user);
        $this->getJson($base.'ltv')->assertOk()->assertJsonPath('data.total_revenue', null)->assertJsonPath('data.customers', 2);
        Sanctum::actingAs($this->actingAsRole(R::ROLE_CLIENT));
        $this->getJson($base.'ltv')->assertForbidden();
    }

    private function portalUser(CrmAccount $a): User
    {
        $u = User::factory()->create(['is_active' => true, 'phone' => '09125556677']);
        $u->assignRole(R::ROLE_CLIENT);
        $u->crmAccounts()->attach($a->id, ['is_primary' => true]);

        return $u;
    }

    public function test_portal_profile_services_documents_and_account_isolation(): void
    {
        $a = CrmAccount::query()->create(['name' => 'Portal Co', 'type' => 'customer', 'mobile' => '09121112222']);
        $other = CrmAccount::query()->create(['name' => 'Else', 'type' => 'customer']);
        DB::table('crm_customer_services')->insert(['account_id' => $a->id, 'kind' => 'hosting', 'name' => 'Host', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('crm_customer_domains')->insert(['account_id' => $a->id, 'domain' => 'portal.ir', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('crm_customer_documents')->insert([
            ['account_id' => $a->id, 'category' => 'brief', 'title' => 'Shared', 'url' => 'https://x.ir/a', 'visible_to_roles' => json_encode(['client']), 'review_status' => 'approved', 'created_at' => now(), 'updated_at' => now()],
            ['account_id' => $a->id, 'category' => 'other', 'title' => 'Internal', 'url' => null, 'visible_to_roles' => null, 'review_status' => 'approved', 'created_at' => now(), 'updated_at' => now()],
        ]);
        Sanctum::actingAs($this->portalUser($a));
        $p = '/api/v1/projects/portal/';
        $this->getJson($p.'profile')->assertOk()->assertJsonPath('data.account.id', $a->id)->assertJsonPath('data.accounts.0.is_primary', true);
        $this->getJson($p.'profile?account_id='.$other->id)->assertForbidden();
        $this->getJson($p.'services')->assertOk()->assertJsonPath('data.0.name', 'Host')->assertJsonPath('meta.domains.0.domain', 'portal.ir');
        $this->getJson($p.'documents')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Shared');

        Sanctum::actingAs($this->actingAsRole(R::ROLE_SYSTEM_MANAGER));
        $this->getJson($p.'profile')->assertForbidden();
    }

    public function test_portal_contract_otp_sign_flow(): void
    {
        Event::fake([ContractSigned::class]);
        $a = CrmAccount::query()->create(['name' => 'Sign Co', 'type' => 'customer']);
        $cid = DB::table('prj_contracts')->insertGetId(['title' => 'Website', 'status' => 'draft', 'amount' => 1000, 'customer_account_id' => $a->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('prj_contract_installments')->insert(['contract_id' => $cid, 'amount' => 500, 'due_date' => now()->addMonth()->toDateString(), 'created_at' => now(), 'updated_at' => now()]);
        $sent = [];
        $this->mock(CrmOutboundMessenger::class, function ($mock) use (&$sent) {
            $mock->shouldReceive('deliverSms')->andReturnUsing(function ($to, $body) use (&$sent) {
                $sent[] = [$to, $body];

                return ['ok' => true];
            });
        });
        Sanctum::actingAs($this->portalUser($a));
        $p = '/api/v1/projects/portal/contracts';
        $this->getJson($p)->assertOk()->assertJsonPath('data.0.can_sign', true);
        $this->getJson("$p/$cid")->assertOk()->assertJsonCount(1, 'data.installments');
        $this->postJson("$p/$cid/sign/otp")->assertOk()->assertJsonPath('data.sent_to', '0912***6677')->assertJsonPath('data.expires_in', 300);
        $this->postJson("$p/$cid/sign/otp")->assertStatus(429)->assertHeader('Retry-After');
        preg_match('/\d{6}/', $sent[0][1], $m);
        $this->postJson("$p/$cid/sign", ['code' => '000000', 'accept' => true])->assertStatus(422)->assertJsonValidationErrors('code');
        $this->postJson("$p/$cid/sign", ['code' => $m[0], 'accept' => true])->assertOk()->assertJsonPath('data.status', 'signed')->assertJsonPath('data.can_sign', false);
        Event::assertDispatched(ContractSigned::class, fn ($e) => $e->contractId === $cid && $e->accountId === $a->id);
        $this->assertDatabaseHas('crm_customer_audit', ['account_id' => $a->id, 'action' => 'portal.contract.signed']);
        $this->postJson("$p/$cid/sign/otp")->assertStatus(422);
    }

    public function test_portal_invoices_pay_and_payment_status(): void
    {
        $a = CrmAccount::query()->create(['name' => 'Pay Co', 'type' => 'customer']);
        $open = $this->invoice($a, 100000, 'issued', now()->toDateString());
        $this->invoice($a, 5000, 'paid', now()->toDateString());
        Sanctum::actingAs($this->actingAsRole(R::ROLE_SYSTEM_MANAGER));
        $this->putJson('/api/v1/integrations/payments/gateways', ['gateways' => ['zarinpal' => [
            'enabled' => true, 'sandbox' => true, 'cash_enabled' => true, 'installment_enabled' => false, 'fee_percent' => 0, 'apply_fee_on_cash' => false,
        ]]])->assertOk();
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response(['data' => ['code' => 100, 'authority' => 'A0000123'], 'errors' => []], 200)]);

        Sanctum::actingAs($this->portalUser($a));
        $p = '/api/v1/projects/portal/';
        $this->getJson($p.'invoices?status=open')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.payable', true)->assertJsonPath('meta.kpis.outstanding', 100000);
        $this->getJson($p.'invoices?status=all')->assertOk()->assertJsonCount(2, 'data');
        $this->postJson($p."invoices/{$open->id}/pay", ['return_url' => 'https://evil.example/x'])->assertStatus(422);
        $pay = $this->postJson($p."invoices/{$open->id}/pay")->assertOk()->assertJsonPath('data.gateway', 'zarinpal');
        $this->assertNotEmpty($pay->json('data.redirect_url'));
        $this->getJson($p.'payments/'.$pay->json('data.payment_id'))->assertOk()->assertJsonPath('data.paid', false)->assertJsonPath('data.invoice_id', $open->id);
        $this->getJson($p.'payments/00000000-0000-0000-0000-000000000000')->assertNotFound();
        $paid = SalesInvoice::query()->where('status', 'paid')->first();
        $this->postJson($p."invoices/{$paid->id}/pay")->assertStatus(422);
    }
}
