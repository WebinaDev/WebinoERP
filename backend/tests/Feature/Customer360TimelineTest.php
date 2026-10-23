<?php

namespace Tests\Feature;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Database\Seeders\RolesAndPermissionsSeeder as R;
use Modules\Crm\Entities\CrmAccount;
use Modules\Crm\Entities\CrmDeal;
use Modules\Crm\Entities\CrmPipeline;
use Modules\Crm\Entities\CrmStage;
use Modules\Crm\Entities\CustomerActivity;
use Modules\Crm\Events\Customer\ContractSigned;
use Modules\Crm\Events\Customer\InvoicePaid;
use Modules\Crm\Listeners\RecordCustomerActivity;
use Modules\Projects\Entities\PrjTicket;
use Modules\Projects\Entities\Project;
use Modules\Sales\Entities\SalesInvoice;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class Customer360TimelineTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    private CrmAccount $acc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->seedLicensedModules();
        $this->acc = CrmAccount::query()->create(['name' => 'Timeline Co', 'type' => 'customer']);
    }

    private function url(string $q = ''): string
    {
        return "/api/v1/crm/customers/{$this->acc->id}/hub/timeline".$q;
    }

    public function test_listeners_record_cross_module_events_idempotently(): void
    {
        $inv = SalesInvoice::query()->create(['number' => 'T-1', 'customer_name' => 'x', 'customer_id' => $this->acc->id, 'total' => 700, 'status' => 'unpaid', 'issue_date' => now()->toDateString()]);
        $types = fn () => CustomerActivity::query()->where('account_id', $this->acc->id)->pluck('type')->all();
        $this->assertContains('invoice.issued', $types());

        $inv->update(['status' => 'paid']);
        $this->assertContains('invoice.paid', $types());
        $this->assertEquals(700, (float) $this->acc->fresh()->lifetime_value);
        $this->assertNotNull($this->acc->fresh()->last_activity_at);

        // Re-delivery of the same event must not duplicate.
        app(RecordCustomerActivity::class)->handle(new InvoicePaid($this->acc->id, $inv->id));
        $this->assertSame(1, CustomerActivity::query()->where('type', 'invoice.paid')->count());

        $inv->update(['status' => 'overdue']);
        $this->assertSame('debtor', $this->acc->fresh()->hub_status);

        $t = PrjTicket::query()->create(['subject' => 'Help', 'body' => 'x', 'status' => 'open', 'customer_account_id' => $this->acc->id, 'priority' => 'low']);
        $t->update(['status' => 'closed']);
        $p = Project::query()->create(['name' => 'Site', 'status' => 'active', 'customer_account_id' => $this->acc->id]);
        $p->update(['status' => 'done']);
        $p->update(['status' => 'archived']);
        $pl = CrmPipeline::query()->create(['name' => 'P', 'is_active' => true]);
        $st = CrmStage::query()->create(['pipeline_id' => $pl->id, 'name' => 'Won', 'sort_order' => 1, 'color' => '#333', 'probability' => 100]);
        CrmDeal::query()->create(['name' => 'Big', 'account_id' => $this->acc->id, 'pipeline_id' => $pl->id, 'stage_id' => $st->id, 'amount' => 5, 'won_at' => now()]);
        event(new ContractSigned($this->acc->id, 77, null, null));
        event(new ContractSigned($this->acc->id, 77, null, null));

        $all = $types();
        foreach (['ticket.opened', 'ticket.closed', 'project.created', 'deal.won', 'contract.signed'] as $t) {
            $this->assertContains($t, $all);
        }
        $this->assertSame(2, CustomerActivity::query()->where('type', 'project.status_changed')->count());
        $this->assertSame(1, CustomerActivity::query()->where('type', 'contract.signed')->count());
        $this->assertSame(CustomerActivity::query()->where('account_id', $this->acc->id)->count(), $this->acc->activities()->count());
    }

    public function test_listener_is_queued(): void
    {
        $this->assertContains(ShouldQueue::class, class_implements(RecordCustomerActivity::class));
        $this->assertTrue(Event::hasListeners(InvoicePaid::class));
        $this->assertTrue(Event::hasListeners(ContractSigned::class));
    }

    public function test_backfill_command_is_idempotent_and_matches_live_keys(): void
    {
        Event::fake(); // simulate history that predates the listeners
        SalesInvoice::query()->create(['number' => 'B-1', 'customer_name' => 'x', 'customer_id' => $this->acc->id, 'total' => 100, 'status' => 'paid', 'issue_date' => now()->subMonth()->toDateString()]);
        PrjTicket::query()->create(['subject' => 'Old', 'body' => 'x', 'status' => 'open', 'customer_account_id' => $this->acc->id, 'priority' => 'low']);
        DB::table('core_licenses')->insert(['license_key' => 'K-9', 'domain' => 'tl.ir', 'product' => 'webino', 'status' => 'active',
            'expires_at' => now()->addYear(), 'crm_account_id' => $this->acc->id, 'created_at' => now(), 'updated_at' => now()]);

        $this->artisan('crm:activities-backfill')->assertSuccessful();
        $n = CustomerActivity::query()->count();
        $this->assertGreaterThanOrEqual(3, $n);
        foreach (['invoice.issued', 'invoice.paid', 'ticket.opened'] as $t) {
            $this->assertTrue(CustomerActivity::query()->where('type', $t)->exists(), $t);
        }
        $this->artisan('crm:activities-backfill', ['--account' => [$this->acc->id]])->assertSuccessful();
        $this->assertSame($n, CustomerActivity::query()->count());
        $this->assertNotNull($this->acc->fresh()->activities_backfilled_at);
    }

    public function test_paged_api_with_filters_cursor_and_rbac(): void
    {
        SalesInvoice::query()->create(['number' => 'P-1', 'customer_name' => 'x', 'customer_id' => $this->acc->id, 'total' => 1, 'status' => 'unpaid', 'issue_date' => now()->subDays(3)->toDateString()]);
        for ($i = 0; $i < 4; $i++) {
            PrjTicket::query()->create(['subject' => "T$i", 'body' => 'x', 'status' => 'open', 'customer_account_id' => $this->acc->id, 'priority' => 'low']);
        }

        Sanctum::actingAs($this->actingAsRole(R::ROLE_SYSTEM_MANAGER));
        $res = $this->getJson($this->url('?per_page=2'))->assertOk();
        $this->assertSame(5, $res->json('meta.total'));
        $this->assertCount(2, $res->json('data'));
        $cursor = $res->json('meta.next_cursor');
        $this->assertNotNull($cursor);
        $next = $this->getJson($this->url('?per_page=2&cursor='.urlencode($cursor)))->assertOk();
        $this->assertEmpty(array_intersect(array_column($res->json('data'), 'id'), array_column($next->json('data'), 'id')));
        $this->getJson($this->url('?per_page=2&page=3'))->assertOk()->assertJsonPath('meta.last_page', 3)->assertJsonCount(1, 'data');

        $this->getJson($this->url('?module=sales'))->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.kind', 'invoice');
        $this->getJson($this->url('?type=ticket.*'))->assertOk()->assertJsonPath('meta.total', 4);
        $this->getJson($this->url('?kind=ticket'))->assertOk()->assertJsonPath('meta.total', 4);
        $this->getJson($this->url('?to='.now()->subDays(2)->toDateString()))->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson($this->url('?from='.now()->subDay()->toDateString()))->assertOk()->assertJsonPath('meta.total', 4);

        // Support role: no billing entries.
        Sanctum::actingAs($this->actingAsRole(R::ROLE_TEAM_MEMBER));
        $kinds = collect($this->getJson($this->url())->assertOk()->json('data'))->pluck('kind');
        $this->assertFalse($kinds->contains('invoice'));

        Sanctum::actingAs($this->actingAsRole(R::ROLE_CLIENT));
        $this->getJson($this->url())->assertForbidden();
    }

    public function test_merge_moves_activities_and_records_merge(): void
    {
        $src = CrmAccount::query()->create(['name' => 'Dup', 'type' => 'customer']);
        SalesInvoice::query()->create(['number' => 'M-1', 'customer_name' => 'x', 'customer_id' => $src->id, 'total' => 1, 'status' => 'unpaid', 'issue_date' => now()->toDateString()]);
        Sanctum::actingAs($this->actingAsRole(R::ROLE_SYSTEM_MANAGER));
        $this->postJson("/api/v1/crm/customers/{$this->acc->id}/hub/merge", ['source_id' => $src->id])->assertOk();
        $this->assertTrue($this->acc->activities()->where('type', 'invoice.issued')->exists());
        $this->assertTrue($this->acc->activities()->where('type', 'customer.merged')->exists());
    }
}
