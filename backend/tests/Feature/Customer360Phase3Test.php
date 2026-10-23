<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Database\Seeders\RolesAndPermissionsSeeder as R;
use Modules\Crm\Entities\CrmAccount;
use Modules\Crm\Entities\CrmBasalamOrder;
use Modules\Crm\Entities\CrmMessage;
use Modules\Marketplace\Entities\MarketplaceBasalamConnection;
use Modules\Marketplace\Entities\MarketplaceOrder;
use Modules\Sales\Entities\SalesInvoice;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class Customer360Phase3Test extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    private CrmAccount $acc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->seedLicensedModules();
        $this->acc = CrmAccount::query()->create(['name' => 'P3 Co', 'type' => 'customer', 'mobile' => '09121112233']);
        DB::table('crm_customer_domains')->insert(['account_id' => $this->acc->id, 'domain' => 'p3co.ir', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function base(): string
    {
        return '/api/v1/crm/customers/'.$this->acc->id.'/hub';
    }

    public function test_orders_list_detail_and_money_gating(): void
    {
        MarketplaceOrder::query()->create(['order_number' => 'MO-9', 'total' => 2000, 'status' => 'paid', 'paid_at' => now(), 'crm_account_id' => $this->acc->id]);
        CrmBasalamOrder::query()->create(['site_domain' => 'p3co.ir', 'external_id' => '77', 'status' => 'pending', 'total' => 500, 'crm_account_id' => $this->acc->id,
            'buyer_name' => 'Ali', 'items' => [['title' => 'Mug', 'qty' => 2, 'price' => 250]], 'ordered_at' => now()->subDay()]);

        Sanctum::actingAs($this->actingAsRole(R::ROLE_SYSTEM_MANAGER));
        $this->getJson($this->base().'/orders')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.kpis.count', 2)->assertJsonPath('meta.kpis.paid_count', 1)->assertJsonPath('meta.can.link_basalam', true);
        $this->getJson($this->base().'/orders?source=basalam&q=ali')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.ref', fn ($v) => str_starts_with($v, 'bs-'));
        $bs = CrmBasalamOrder::query()->first();
        $this->getJson($this->base().'/orders/bs-'.$bs->id)->assertOk()->assertJsonPath('data.items.0.title', 'Mug')->assertJsonPath('data.items.0.unit_price', 250);
        $this->getJson($this->base().'/orders/bs-999')->assertNotFound();

        Sanctum::actingAs($this->actingAsRole(R::ROLE_CLIENT));
        $this->getJson($this->base().'/orders')->assertForbidden();
    }

    public function test_basalam_store_link_and_unlink(): void
    {
        $c = MarketplaceBasalamConnection::query()->create(['site_url' => 'https://other.ir', 'status' => 'connected']);
        CrmBasalamOrder::query()->create(['connection_id' => $c->id, 'site_domain' => 'other.ir', 'external_id' => '1', 'status' => 'paid', 'total' => 1]);
        $auto = MarketplaceBasalamConnection::query()->create(['site_url' => 'https://p3co.ir', 'status' => 'connected']);
        $this->assertSame($this->acc->id, (int) $auto->crm_account_id);

        Sanctum::actingAs($this->actingAsRole(R::ROLE_SYSTEM_MANAGER));
        $this->getJson($this->base().'/basalam/stores')->assertOk()->assertJsonCount(1, 'data');
        $this->postJson($this->base().'/basalam/stores', ['connection_id' => $c->id])->assertOk()->assertJsonPath('data.linked', true);
        $this->assertSame($this->acc->id, (int) CrmBasalamOrder::query()->first()->crm_account_id);
        $other = CrmAccount::query()->create(['name' => 'Other', 'type' => 'customer']);
        $this->postJson('/api/v1/crm/customers/'.$other->id.'/hub/basalam/stores', ['connection_id' => $c->id])->assertStatus(409);
        $this->deleteJson($this->base().'/basalam/stores/'.$c->id)->assertNoContent();
        $this->assertNull($c->fresh()->crm_account_id);
    }

    public function test_accounting_summary_ledger_invoices_payments(): void
    {
        SalesInvoice::query()->create(['number' => 'A-1', 'customer_name' => 'x', 'customer_id' => $this->acc->id, 'total' => 1000, 'status' => 'unpaid', 'issue_date' => now()->subDays(75)->toDateString()]);
        SalesInvoice::query()->create(['number' => 'A-2', 'customer_name' => 'x', 'customer_id' => $this->acc->id, 'total' => 400, 'status' => 'paid', 'issue_date' => now()->subDays(10)->toDateString()]);
        MarketplaceOrder::query()->create(['order_number' => 'MO-2', 'total' => 300, 'status' => 'paid', 'paid_at' => now()->subDays(2), 'crm_account_id' => $this->acc->id]);

        Sanctum::actingAs($this->actingAsRole(R::ROLE_SYSTEM_MANAGER));
        $s = $this->getJson($this->base().'/accounting')->assertOk();
        $s->assertJsonPath('data.overdue_count', 1)->assertJsonPath('data.aging.d31_60', 1000)->assertJsonPath('data.total_invoiced', 1400);
        $l = $this->getJson($this->base().'/accounting/ledger')->assertOk()->json();
        $this->assertCount(3, $l['data']);
        $this->assertSame('invoice', $l['data'][0]['kind']);
        $this->assertEquals(-1100, $l['meta']['closing_balance']);
        $this->getJson($this->base().'/accounting/invoices?status=overdue')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.overdue_days', 45)->assertJsonPath('meta.kpis.paid', 1);
        $this->getJson($this->base().'/accounting/payments')->assertOk()->assertJsonPath('data.0.id', fn ($v) => str_starts_with($v, 'mo-'));
    }

    public function test_sms_history_and_send_with_permission_and_opt_out(): void
    {
        CrmMessage::query()->create(['channel' => 'sms', 'related_type' => 'account', 'related_id' => $this->acc->id, 'to_address' => '09121112233', 'body' => 'old', 'status' => 'sent']);
        Sanctum::actingAs($this->actingAsRole(R::ROLE_SYSTEM_MANAGER));
        $this->getJson($this->base().'/sms')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.can.send', true)->assertJsonPath('meta.recipients.0.kind', 'account');
        $this->postJson($this->base().'/sms', ['body' => 'Hi {{name}}'])->assertCreated()->assertJsonPath('data.body', 'Hi P3 Co');
        $this->assertSame(2, CrmMessage::query()->where('crm_account_id', $this->acc->id)->count());
        $this->postJson($this->base().'/sms', [])->assertStatus(422);
        $this->acc->forceFill(['marketing_sms_opt_out' => true])->save();
        $this->postJson($this->base().'/sms', ['body' => 'promo', 'purpose' => 'marketing'])->assertStatus(422);

        Sanctum::actingAs($this->actingAsRole(R::ROLE_TEAM_MEMBER));
        $this->postJson($this->base().'/sms', ['body' => 'x'])->assertForbidden();
    }
}
