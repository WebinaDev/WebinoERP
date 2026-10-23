<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Crm\Entities\CrmAccount;
use Modules\Crm\Entities\CrmBasalamOrder;
use Modules\Crm\Entities\CrmMessage;
use Modules\Crm\Entities\CustomerActivity;
use Modules\Integrations\Entities\PaymentIntent;
use Modules\Marketplace\Entities\MarketplaceBasalamConnection;
use Modules\Marketplace\Entities\MarketplaceOrder;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class Customer360HooksTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    private CrmAccount $acc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->seedLicensedModules();
        $this->acc = CrmAccount::query()->create(['name' => 'Hook Co', 'type' => 'customer', 'mobile' => '09121234567']);
        \DB::table('crm_customer_domains')->insert(['account_id' => $this->acc->id, 'domain' => 'hookco.ir', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function types(): array
    {
        return CustomerActivity::query()->where('account_id', $this->acc->id)->pluck('type')->all();
    }

    public function test_marketplace_payment_and_site_hooks(): void
    {
        $o = MarketplaceOrder::query()->create(['order_number' => 'MO-1', 'total' => 1000, 'status' => 'pending', 'crm_account_id' => $this->acc->id]);
        $this->assertContains('order.placed', $this->types());
        $o->update(['paid_at' => now(), 'status' => 'paid']);
        $this->assertSame(1, CustomerActivity::query()->where('type', 'payment.recorded')->where('subject_type', 'marketplace_order')->count());

        $pi = PaymentIntent::query()->create(['public_id' => (string) Str::uuid(), 'payable_type' => 'generic', 'payable_id' => 'x', 'mode' => 'cash',
            'gateway' => 'zarinpal', 'base_amount' => 5000, 'total_amount' => 5000, 'callback_token' => 't', 'status' => 'redirected', 'domain' => 'hookco.ir']);
        $this->assertSame(1, CustomerActivity::query()->where('type', 'payment.recorded')->count());
        $pi->update(['status' => 'settled', 'settled_at' => now()]);
        $pi->update(['status' => 'settled']);
        $this->assertSame(1, CustomerActivity::query()->where('type', 'payment.recorded')->where('subject_type', 'payment')->count());

        $s = WebinoSiteProvision::query()->create(['slug' => 'hook', 'domain' => 'hookco.ir', 'status' => 'draft', 'crm_account_id' => $this->acc->id]);
        $this->assertNotContains('site.provisioned', $this->types());
        $s->update(['status' => 'ready']);
        $this->assertContains('site.provisioned', $this->types());
    }

    public function test_sms_log_is_linked_to_customer_by_mobile(): void
    {
        $m = CrmMessage::query()->create(['channel' => 'sms', 'related_type' => 'lead', 'related_id' => 99, 'to_address' => '+989121234567', 'body' => 'hi', 'status' => 'sent']);
        $this->assertSame($this->acc->id, (int) $m->fresh()->crm_account_id);
        $this->assertContains('sms.sent', $this->types());
        $other = CrmMessage::query()->create(['channel' => 'sms', 'related_type' => 'lead', 'related_id' => 1, 'to_address' => '09350000000', 'body' => 'x', 'status' => 'sent']);
        $this->assertNull($other->fresh()->crm_account_id);
    }

    public function test_basalam_connection_autolinks_and_ingest_mirrors_orders(): void
    {
        $c = MarketplaceBasalamConnection::query()->create(['site_url' => 'https://hookco.ir', 'vendor_id' => '77', 'status' => 'connected']);
        $this->assertSame($this->acc->id, (int) $c->fresh()->crm_account_id);

        $payload = ['site_url' => 'https://hookco.ir', 'orders' => [['id' => 'B1', 'status' => 'new', 'total' => 250000, 'items' => [['title' => 'x', 'qty' => 1]]]]];
        $this->postJson('/api/webinocrm/v1/crm/basalam/orders', $payload)->assertOk()->assertJsonPath('data.saved', 1)->assertJsonPath('data.linked', true);
        $this->postJson('/api/webinocrm/v1/crm/basalam/orders', $payload)->assertOk()->assertJsonPath('data.saved', 0);
        $this->assertSame(1, CrmBasalamOrder::query()->count());
        $this->assertContains('basalam.order', $this->types());
    }
}
