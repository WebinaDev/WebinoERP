<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Database\Seeders\RolesAndPermissionsSeeder as R;
use Modules\Crm\Entities\CrmAccount;
use Modules\Projects\Entities\PrjTicket;
use Modules\Sales\Entities\SalesInvoice;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class CustomerHubApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    private CrmAccount $acc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->seedLicensedModules();
        $this->acc = CrmAccount::query()->create(['name' => 'Hub Co', 'type' => 'customer', 'account_code' => 'C-1']);
        SalesInvoice::query()->create(['number' => 'H-1', 'customer_name' => 'Hub Co', 'customer_id' => $this->acc->id, 'total' => 1000, 'status' => 'paid', 'issue_date' => now()->subMonths(2)->toDateString()]);
        SalesInvoice::query()->create(['number' => 'H-2', 'customer_name' => 'Hub Co', 'customer_id' => $this->acc->id, 'total' => 400, 'status' => 'unpaid', 'issue_date' => now()->subDays(45)->toDateString()]);
        PrjTicket::query()->create(['subject' => 'Help', 'body' => 'x', 'status' => 'open', 'customer_account_id' => $this->acc->id, 'priority' => 'urgent']);
        DB::table('core_licenses')->insert(['license_key' => 'K-1234', 'domain' => 'hubco.ir', 'product' => 'webino', 'status' => 'active',
            'expires_at' => now()->addDays(10), 'crm_account_id' => $this->acc->id, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_admin_gets_full_hub_summary_with_kpis_and_alerts(): void
    {
        Sanctum::actingAs($this->actingAsRole(R::ROLE_SYSTEM_MANAGER));
        $res = $this->getJson("/api/v1/crm/customers/{$this->acc->id}/hub")->assertOk();
        $res->assertJsonPath('data.header.code', 'C-1')
            ->assertJsonPath('data.kpis.lifetime_revenue', 1000)
            ->assertJsonPath('data.kpis.outstanding', 400)
            ->assertJsonPath('data.kpis.open_tickets', 1)
            ->assertJsonPath('data.kpis.active_licenses', 1);
        $codes = collect($res->json('data.alerts'))->pluck('code');
        $this->assertTrue($codes->contains('overdue_debt'));
        $this->assertTrue($codes->contains('license_expiring'));
        $this->assertContains('access', $res->json('data.sections'));
        $score = $res->json('data.health.score');
        $this->assertIsInt($score);
        $this->assertTrue($score >= 0 && $score <= 100);
        $lic = $res->json('data.licenses_preview.0');
        $this->assertSame(14, $lic['alert']);
        $this->assertStringNotContainsString('K-1234', (string) $lic['key_masked']);
    }

    public function test_paginated_sections(): void
    {
        Sanctum::actingAs($this->actingAsRole(R::ROLE_SYSTEM_MANAGER));
        $id = $this->acc->id;
        $this->getJson("/api/v1/crm/customers/$id/hub/invoices?per_page=5")->assertOk()->assertJsonPath('meta.total', 2);
        $this->getJson("/api/v1/crm/customers/$id/hub/invoices?status=unpaid")->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson("/api/v1/crm/customers/$id/hub/tickets")->assertOk()->assertJsonPath('meta.total', 1);
        $this->assertGreaterThan(0, $this->getJson("/api/v1/crm/customers/$id/hub/timeline")->assertOk()->json('meta.total'));
        foreach (['identity', 'assets', 'billing', 'sales', 'usage', 'access', 'relations', 'notes', 'analytics', 'documents', 'payments', 'communications', 'licenses', 'wallet_ledger'] as $s) {
            $this->getJson("/api/v1/crm/customers/$id/hub/$s")->assertOk();
        }
        $this->getJson("/api/v1/crm/customers/$id/hub/nope")->assertNotFound();
        $this->getJson('/api/v1/crm/customers/999999/hub')->assertNotFound();
    }

    public function test_finance_role_sees_billing_but_not_identity_or_access(): void
    {
        Sanctum::actingAs($this->actingAsRole(R::ROLE_FINANCE_MANAGER));
        $id = $this->acc->id;
        $res = $this->getJson("/api/v1/crm/customers/$id/hub")->assertOk();
        $this->assertContains('billing', $res->json('data.sections'));
        $this->assertNotContains('identity', $res->json('data.sections'));
        $this->assertNotContains('access', $res->json('data.sections'));
        $res->assertJsonPath('data.kpis.lifetime_revenue', 1000);
        $this->assertArrayNotHasKey('identity', $res->json('data'));
        $this->getJson("/api/v1/crm/customers/$id/hub/invoices")->assertOk();
        $this->getJson("/api/v1/crm/customers/$id/hub/identity")->assertForbidden();
        $this->getJson("/api/v1/crm/customers/$id/hub/access")->assertForbidden();
        $this->postJson("/api/v1/crm/customers/$id/hub/actions/suspend", ['reason' => 'x'])->assertForbidden();
    }

    public function test_support_role_sees_tickets_but_no_financials(): void
    {
        Sanctum::actingAs($this->actingAsRole(R::ROLE_TEAM_MEMBER));
        $id = $this->acc->id;
        $res = $this->getJson("/api/v1/crm/customers/$id/hub")->assertOk();
        $this->assertContains('support', $res->json('data.sections'));
        $this->assertNotContains('billing', $res->json('data.sections'));
        $res->assertJsonPath('data.kpis.lifetime_revenue', null)->assertJsonPath('data.kpis.open_tickets', 1);
        $this->assertFalse(collect($res->json('data.alerts'))->pluck('code')->contains('overdue_debt'));
        $this->getJson("/api/v1/crm/customers/$id/hub/tickets")->assertOk();
        $this->getJson("/api/v1/crm/customers/$id/hub/invoices")->assertForbidden();
        $kinds = collect($this->getJson("/api/v1/crm/customers/$id/hub/timeline")->assertOk()->json('data'))->pluck('kind');
        $this->assertFalse($kinds->contains('invoice'));
        $this->postJson("/api/v1/crm/customers/$id/hub/actions/ticket", ['subject' => 'From hub'])->assertCreated();
        $this->postJson("/api/v1/crm/customers/$id/hub/actions/invoice", ['items' => [['title' => 'a', 'price' => 1]]])->assertForbidden();
    }

    public function test_client_role_is_forbidden(): void
    {
        Sanctum::actingAs($this->actingAsRole(R::ROLE_CLIENT));
        $this->getJson("/api/v1/crm/customers/{$this->acc->id}/hub")->assertForbidden();
    }

    public function test_actions_write_and_audit(): void
    {
        $admin = $this->actingAsRole(R::ROLE_SYSTEM_MANAGER);
        Sanctum::actingAs($admin);
        $id = $this->acc->id;

        $this->patchJson("/api/v1/crm/customers/$id/hub/profile", ['customer_kind' => 'legal', 'national_id' => '10380284790', 'allowed_ips' => ['1.2.3.4']])->assertOk()
            ->assertJsonPath('data.profile.customer_kind', 'legal');
        $this->postJson("/api/v1/crm/customers/$id/hub/contacts", ['first_name' => 'Ali', 'contact_role' => 'finance', 'is_primary' => true])->assertCreated();

        $inv = $this->postJson("/api/v1/crm/customers/$id/hub/actions/invoice", ['items' => [['title' => 'Plan', 'price' => 300]]])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/crm/customers/$id/hub/actions/payment", ['amount' => 300, 'method' => 'card_to_card', 'invoice_id' => $inv])->assertCreated()
            ->assertJsonPath('data.invoice_status', 'paid');

        $lic = $this->postJson("/api/v1/crm/customers/$id/hub/actions/license", ['domain' => 'shop.hubco.ir', 'license_type' => 'yearly', 'usage_limits' => ['users' => 5]])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/crm/customers/$id/hub/actions/license/$lic/renew", ['months' => 12])->assertOk();

        $this->postJson("/api/v1/crm/customers/$id/hub/domains", ['domain' => 'hubco.com', 'expires_at' => now()->addDays(20)->toDateString()])->assertCreated();
        $this->postJson("/api/v1/crm/customers/$id/hub/services", ['kind' => 'gateway', 'name' => 'Zarinpal', 'secret' => 'abcdefSECRET9876'])->assertCreated()
            ->assertJsonPath('data.key_last4', '9876');
        $this->assertDatabaseMissing('crm_customer_services', ['key_last4' => 'abcdefSECRET9876']);

        $other = CrmAccount::query()->create(['name' => 'Sister', 'type' => 'customer']);
        $this->postJson("/api/v1/crm/customers/$id/hub/relations", ['related_account_id' => $other->id, 'relation_type' => 'related'])->assertCreated();
        $this->postJson("/api/v1/crm/customers/$id/hub/alerts", ['kind' => 'pinned_note', 'body' => 'VIP — call before changes'])->assertCreated();
        $this->postJson("/api/v1/crm/customers/$id/hub/onboarding", ['preset' => true])->assertCreated();
        $this->postJson("/api/v1/crm/customers/$id/hub/actions/note", ['body' => 'Called client'])->assertCreated();
        $this->postJson("/api/v1/crm/customers/$id/hub/actions/project", ['name' => 'Redesign'])->assertCreated();
        $this->postJson("/api/v1/crm/customers/$id/hub/actions/owner", ['owner_id' => $admin->id])->assertOk()->assertJsonPath('data.owner.id', $admin->id);
        $this->postJson("/api/v1/crm/customers/$id/hub/actions/suspend", ['reason' => 'debt', 'confirm' => true])->assertOk()->assertJsonPath('data.status', 'blocked');
        $this->postJson("/api/v1/crm/customers/$id/hub/actions/activate", ['confirm' => true])->assertOk()->assertJsonPath('data.status', 'active');

        $this->assertGreaterThanOrEqual(8, DB::table('crm_customer_audit')->where('account_id', $id)->count());
        $hub = $this->getJson("/api/v1/crm/customers/$id/hub")->assertOk();
        $hub->assertJsonPath('data.kpis.lifetime_revenue', 1300);
        $this->assertNotEmpty($hub->json('data.notes.pinned'));
        $this->assertCount(8, $hub->json('data.notes.onboarding'));
        $this->assertSame('hubco.com', $this->getJson("/api/v1/crm/customers/$id/hub/assets")->json('data.domains.0.domain'));
        $this->assertNotEmpty($this->getJson("/api/v1/crm/customers/$id/hub/relations")->json('data.related'));
        $this->assertCount(12, $this->getJson("/api/v1/crm/customers/$id/hub/analytics")->json('data.revenue_monthly'));
    }

    public function test_document_visibility_by_role(): void
    {
        Sanctum::actingAs($this->actingAsRole(R::ROLE_SYSTEM_MANAGER));
        $id = $this->acc->id;
        $this->postJson("/api/v1/crm/customers/$id/hub/documents", ['category' => 'national_card', 'title' => 'ID', 'visible_to_roles' => ['system_manager']])->assertCreated();
        $doc = $this->postJson("/api/v1/crm/customers/$id/hub/documents", ['category' => 'brief', 'title' => 'Brief'])->assertCreated()->json('data.id');
        $this->patchJson("/api/v1/crm/customers/$id/hub/documents/$doc", ['review_status' => 'approved'])->assertOk();

        Sanctum::actingAs($this->actingAsRole(R::ROLE_CRM_SPECIALIST));
        $titles = collect($this->getJson("/api/v1/crm/customers/$id/hub/documents")->assertOk()->json('data'))->pluck('title');
        $this->assertTrue($titles->contains('Brief'));
        $this->assertFalse($titles->contains('ID'));
    }

    public function test_site_provisions_feed_assets_and_kpis(): void
    {
        Sanctum::actingAs($this->actingAsRole(R::ROLE_SYSTEM_MANAGER));
        WebinoSiteProvision::query()->create(['crm_account_id' => $this->acc->id, 'slug' => 'hubco', 'domain' => 'hubco.ir', 'status' => 'ready']);
        $this->getJson("/api/v1/crm/customers/{$this->acc->id}/hub")->assertOk()->assertJsonPath('data.kpis.active_sites', 1);
        $this->getJson("/api/v1/crm/customers/{$this->acc->id}/hub/assets")->assertOk()->assertJsonPath('data.sites.0.domain', 'hubco.ir');
    }
}
