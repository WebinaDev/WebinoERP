<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Entities\SystemModule;
use Modules\Core\Mail\ScheduledReportMail;
use Modules\Crm\Entities\CrmAccount;
use Modules\Crm\Entities\CrmCompany;
use Modules\Crm\Entities\CrmContact;
use Modules\Crm\Entities\CrmDeal;
use Modules\Crm\Entities\CrmPipeline;
use Modules\Crm\Entities\CrmStage;
use Modules\Integrations\Entities\CalendarAccount;
use Modules\Integrations\Entities\ChatBridge;
use Modules\Integrations\Entities\InboundEvent;
use Modules\Projects\Entities\ProjectTask;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class ProductionGradeApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        foreach (['crm', 'projects', 'integrations', 'ai_content'] as $slug) {
            SystemModule::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => strtoupper($slug), 'is_active' => true]
            );
        }
    }

    public function test_oauth_refresh_stores_rotated_token_and_marks_reauth(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::sequence()
                ->push([
                    'access_token' => 'fresh-access',
                    'refresh_token' => 'rotated-refresh',
                    'expires_in' => 3600,
                ])
                ->push(['error' => 'invalid_grant'], 400),
            'https://www.googleapis.com/*' => Http::response(['items' => []]),
        ]);
        $account = CalendarAccount::query()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'access_token' => 'old',
            'refresh_token' => 'old-refresh',
            'expires_at' => now()->subMinute(),
            'calendar_id' => 'primary',
            'status' => 'active',
        ]);
        $this->postJson('/api/v1/integrations/calendars/'.$account->id.'/sync')->assertOk();
        $account->refresh();
        $this->assertSame('rotated-refresh', $account->refresh_token);
        $this->assertSame('active', $account->status);
        $this->assertSame(0, (int) $account->refresh_attempts);

        $account->update(['expires_at' => now()->subMinute(), 'refresh_attempts' => 2]);
        $this->postJson('/api/v1/integrations/calendars/'.$account->id.'/sync')->assertStatus(500);
        $account->refresh();
        $this->assertSame('needs_reauth', $account->status);
        $this->assertSame(3, (int) $account->refresh_attempts);
    }

    public function test_public_webhook_is_queued_and_failed_events_retry(): void
    {
        ChatBridge::query()->create([
            'provider' => 'telegram',
            'name' => 'Ops',
            'bot_token' => '123:abc',
            'webhook_secret' => 'secret',
            'inbound_commands' => true,
            'enabled' => true,
        ]);
        $this->postJson('/api/v1/integrations/bridges/webhook/telegram', [
            'message' => ['message_id' => 9, 'text' => '/task Queue me', 'chat' => ['id' => 42]],
        ])->assertStatus(401);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'secret')
            ->postJson('/api/v1/integrations/bridges/webhook/telegram', [
                'message' => ['message_id' => 9, 'text' => '/task Queue me', 'chat' => ['id' => 42]],
            ])->assertOk()->assertJsonPath('queued', true);
        $this->assertDatabaseHas('prj_tasks', ['title' => 'Queue me']);
        $this->assertDatabaseHas('int_inbound_events', ['provider' => 'telegram', 'status' => 'done']);

        $bridge = ChatBridge::query()->first();
        InboundEvent::query()->create([
            'provider' => 'telegram',
            'bridge_id' => $bridge->id,
            'external_id' => '11',
            'payload' => ['message' => ['message_id' => 11, 'text' => '/task Retry me', 'chat' => ['id' => 7]]],
            'status' => 'failed',
            'attempts' => 1,
            'available_at' => now()->subMinute(),
        ]);
        Artisan::call('webino:webhooks:retry');
        $this->assertDatabaseHas('prj_tasks', ['title' => 'Retry me']);
    }

    public function test_mailbox_spam_filter_and_multi_inbox_search(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);
        $account = CrmAccount::query()->create(['name' => 'ACME', 'type' => 'customer']);
        CrmContact::query()->create([
            'account_id' => $account->id,
            'first_name' => 'Sara',
            'last_name' => 'Ahmadi',
            'email' => 'sara@example.com',
        ]);
        $first = $this->postJson('/api/v1/integrations/mailbox', [
            'provider' => 'imap',
            'email' => 'one@webino.local',
        ])->assertCreated()->json('data.id');
        $second = $this->postJson('/api/v1/integrations/mailbox', [
            'provider' => 'imap',
            'email' => 'two@webino.local',
        ])->assertCreated()->json('data.id');

        $this->postJson('/api/v1/integrations/mailbox/'.$first.'/import', [
            'from_email' => 'sara@example.com',
            'subject' => 'برنده شدید هدیه رایگان',
            'body' => 'click here now',
            'external_id' => 'spam-1',
        ])->assertCreated();
        $this->assertDatabaseHas('int_email_messages', ['external_id' => 'spam-1', 'is_spam' => true, 'crm_account_id' => null]);

        $this->postJson('/api/v1/integrations/mailbox/'.$first.'/import', [
            'from_email' => 'sara@example.com',
            'subject' => 'Roadmap',
            'body' => 'Quarter plan',
            'external_id' => 'ok-1',
        ])->assertCreated();
        $this->postJson('/api/v1/integrations/mailbox/'.$second.'/import', [
            'from_email' => 'other@example.com',
            'subject' => 'Roadmap copy',
            'body' => 'Shared note',
            'external_id' => 'ok-2',
        ])->assertCreated();

        $hits = $this->getJson('/api/v1/integrations/mailbox/search?q=Roadmap')->assertOk()->json('data');
        $this->assertCount(2, $hits);
        $spam = $this->getJson('/api/v1/integrations/mailbox/search?q=رایگان&include_spam=1')->assertOk()->json('data');
        $this->assertNotEmpty($spam);
        $hidden = $this->getJson('/api/v1/integrations/mailbox/search?q=رایگان')->assertOk()->json('data');
        $this->assertSame([], $hidden);
    }

    public function test_cpq_dependencies_hierarchy_and_approval(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);
        config(['integrations.cpq.approval_percent' => 15]);
        $host = $this->postJson('/api/v1/crm/catalog-products', ['name' => 'Host', 'tax_percent' => 0, 'category' => 'web'])->assertCreated()->json('data.id');
        $ssl = $this->postJson('/api/v1/crm/catalog-products', ['name' => 'SSL', 'tax_percent' => 0])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/crm/product-rules', [
            'product_id' => $host,
            'related_product_id' => $ssl,
            'kind' => 'requires',
            'min_qty' => 1,
        ])->assertCreated();
        $parent = $this->postJson('/api/v1/crm/discount-nodes', [
            'scope' => 'product',
            'scope_key' => (string) $host,
            'name' => 'Line',
            'percent' => 10,
            'stackable' => true,
        ])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/crm/discount-nodes', [
            'parent_id' => $parent,
            'scope' => 'product',
            'scope_key' => (string) $host,
            'name' => 'Extra',
            'percent' => 10,
            'stackable' => true,
        ])->assertCreated();
        $book = $this->postJson('/api/v1/crm/price-books', [
            'name' => 'List',
            'currency_code' => 'IRR',
            'items' => [
                ['product_id' => $host, 'min_qty' => 1, 'unit_price' => 1000, 'discount_percent' => 0],
                ['product_id' => $ssl, 'min_qty' => 1, 'unit_price' => 100, 'discount_percent' => 0],
            ],
        ])->assertCreated()->json('data.id');
        $deal = $this->deal($user);
        $this->postJson('/api/v1/crm/deals/'.$deal->id.'/quote', [
            'price_book_id' => $book,
            'lines' => [['product_id' => $host, 'qty' => 1]],
        ])->assertStatus(422)->assertJsonPath('message', 'missing_dependency');

        $quote = $this->postJson('/api/v1/crm/deals/'.$deal->id.'/quote', [
            'price_book_id' => $book,
            'lines' => [
                ['product_id' => $host, 'qty' => 1],
                ['product_id' => $ssl, 'qty' => 1],
            ],
        ])->assertOk()->json('data');
        $this->assertEquals(190.0, (float) $quote['discount']);
        $this->assertSame('pending', $quote['approval']['status']);
        $approvalId = (int) $quote['approval']['id'];
        $this->postJson('/api/v1/crm/quote-approvals/'.$approvalId.'/decide', ['status' => 'approved'])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');
    }

    public function test_moadian_packet_submit_and_retry(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);
        config([
            'integrations.moadian.base_url' => 'https://moadian.test',
            'integrations.moadian.client_id' => 'cid',
            'integrations.moadian.client_secret' => 'sec',
            'integrations.moadian.fiscal_id' => 'A1B2C3',
            'integrations.moadian.retry_times' => 3,
        ]);
        $calls = 0;
        Http::fake(function ($request) use (&$calls) {
            if (str_contains($request->url(), '/auth/token')) {
                return Http::response(['access_token' => 'tok', 'expires_in' => 3600]);
            }
            $calls++;
            if ($calls === 1) {
                return Http::response(['error' => 'busy'], 503);
            }

            return Http::response(['referenceNumber' => 'REF9'], 200);
        });
        $invoice = $this->postJson('/api/v1/crm/einvoices', [
            'buyer_type' => 'legal',
            'buyer_national_id' => '10888888888',
            'buyer_economic_code' => '422222222222',
            'seller_economic_code' => '411111111111',
            'items' => [[
                'description' => 'Design',
                'qty' => 2,
                'fee' => 1000,
                'discount' => 100,
                'vat_rate' => 10,
                'commodity_code' => '2720000000000',
            ]],
        ])->assertCreated()->json('data');
        $this->assertSame($invoice['document']['taxid'], $invoice['document']['moadian']['header']['taxid']);
        $this->assertSame(2000, (int) $invoice['document']['moadian']['header']['tprdis']);
        $this->assertSame('2720000000000', $invoice['document']['moadian']['body'][0]['sstid']);

        $this->postJson('/api/v1/crm/einvoices/'.$invoice['id'].'/submit')->assertOk()
            ->assertJsonPath('data.status', 'retry');
        $this->assertDatabaseHas('crm_einvoices', ['id' => $invoice['id'], 'status' => 'retry', 'attempts' => 1]);
        \Modules\Crm\Entities\CrmEInvoice::query()->whereKey($invoice['id'])->update(['next_retry_at' => now()->subMinute()]);
        Artisan::call('webino:moadian:retry');
        $this->assertDatabaseHas('crm_einvoices', ['id' => $invoice['id'], 'status' => 'accepted', 'reference_number' => 'REF9']);
    }

    public function test_multi_touch_attribution_and_roi(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);
        $deal = $this->deal($user);
        $deal->update(['amount' => 1000, 'won_at' => now(), 'currency_code' => 'IRR']);
        foreach (['spring', 'spring', 'retarget'] as $index => $campaign) {
            $this->postJson('/api/v1/crm/touchpoints', [
                'deal_id' => $deal->id,
                'utm_campaign' => $campaign,
                'utm_source' => 'ads',
                'channel' => 'web',
                'occurred_at' => now()->addMinutes($index)->toIso8601String(),
            ])->assertCreated();
        }
        $this->postJson('/api/v1/crm/campaign-spends', [
            'campaign_key' => 'spring',
            'spent' => 200,
            'currency_code' => 'IRR',
        ])->assertCreated();
        $rows = collect($this->getJson('/api/v1/crm/attribution/roi?model=position')->assertOk()->json('data'));
        $spring = $rows->firstWhere('campaign', 'spring');
        $retarget = $rows->firstWhere('campaign', 'retarget');
        $this->assertEquals(600.0, (float) $spring['revenue']);
        $this->assertEquals(400.0, (float) $retarget['revenue']);
        $this->assertEquals(2.0, (float) $spring['roi']);
    }

    public function test_timeline_shift_and_leave_calendar_conflict(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);
        $task = ProjectTask::query()->create([
            'title' => 'Design',
            'status' => 'open',
            'duration_days' => 2,
            'assignee_id' => $user->id,
            'start_at' => now()->startOfDay(),
            'due_at' => now()->startOfDay(),
            'created_by' => $user->id,
        ]);
        $today = now()->toDateString();
        $this->postJson('/api/v1/projects/planning/leave', [
            'user_id' => $user->id,
            'starts_on' => $today,
            'ends_on' => $today,
        ])->assertCreated();
        $row = $this->getJson('/api/v1/projects/planning/capacity?user_id='.$user->id.'&from='.$today.'&to='.$today)
            ->assertOk()->json('data.0');
        $this->assertSame(0, (int) $row['available_hours']);
        $this->assertGreaterThan(0, (int) $row['conflict_count']);
        $this->assertSame('leave_task', $row['conflicts'][0]['kind']);

        $next = now()->addDays(3)->toDateString();
        $this->postJson('/api/v1/projects/planning/tasks/'.$task->id.'/shift', ['start_on' => $next])->assertOk();
        $task->refresh();
        $this->assertSame($next, $task->start_at->toDateString());
        $board = $this->getJson('/api/v1/projects/planning/timeline')->assertOk()->json('data');
        $this->assertSame($next, collect($board['tasks'])->firstWhere('id', $task->id)['start_on']);
    }

    public function test_saml_idp_signs_a_response_the_acs_accepts(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);
        $provider = $this->postJson('/api/v1/core/sso', [
            'name' => 'IdP',
            'protocol' => 'saml',
            'enabled' => true,
        ])->assertCreated()->json('data.id');
        $this->get('/api/v1/core/sso/saml/'.$provider.'/metadata')
            ->assertOk()
            ->assertSee('IDPSSODescriptor', false)
            ->assertSee('AssertionConsumerService', false);
        $request = base64_encode('<samlp:AuthnRequest xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" ID="_req" Version="2.0" IssueInstant="2026-10-03T00:00:00Z" AssertionConsumerServiceURL="https://sp.example/acs"><saml:Issuer>https://sp.example</saml:Issuer></samlp:AuthnRequest>');
        $issued = $this->postJson('/api/v1/core/sso/saml/idp/sso', ['SAMLRequest' => $request])->assertOk()->json('data.SAMLResponse');
        $this->assertNotEmpty($issued);
        $this->postJson('/api/v1/core/sso/saml/'.$provider.'/acs', ['SAMLResponse' => $issued])
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_report_join_schedule_and_sandbox_audit_export(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);
        Mail::fake();
        $company = CrmCompany::query()->create(['name' => 'Northwind', 'economic_code' => '400']);
        $deal = $this->deal($user);
        $deal->update(['company_id' => $company->id, 'name' => 'Joined deal']);
        $report = $this->postJson('/api/v1/core/bi-reports', [
            'name' => 'Deal companies',
            'source' => 'deals',
            'columns' => ['id', 'name'],
            'joins' => [['source' => 'companies', 'columns' => ['name']]],
            'schedule' => ['frequency' => 'daily', 'email' => 'ops@webino.local'],
        ])->assertCreated()->json('data.id');
        $rows = $this->getJson('/api/v1/core/bi-reports/'.$report.'/run')->assertOk()->json('data.rows');
        $this->assertSame('Northwind', collect($rows)->firstWhere('name', 'Joined deal')['companies_name']);
        Artisan::call('webino:reports:send');
        Mail::assertSent(ScheduledReportMail::class, fn (ScheduledReportMail $mail) => $mail->hasTo('ops@webino.local'));

        $this->withHeader('X-Webino-Sandbox', '1')->postJson('/api/v1/crm/einvoices', [
            'buyer_type' => 'natural',
            'buyer_national_id' => '0011111111',
            'items' => [['description' => 'Note', 'qty' => 1, 'fee' => 10]],
        ])->assertCreated();
        $this->getJson('/api/v1/core/compliance/audit?sandbox=1')->assertOk()
            ->assertJsonPath('data.0.sandbox', true)
            ->assertJsonPath('data.0.action', 'einvoice.draft');
        $this->get('/api/v1/core/compliance/audit.csv?sandbox=1')->assertOk();
    }

    public function test_direct_publish_and_module_health(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);
        Http::fake([
            'https://graph.facebook.com/*' => Http::sequence()
                ->push(['id' => 'creation-1'])
                ->push(['id' => 'ig-post-1']),
            'https://api.linkedin.com/*' => Http::response(['id' => 'urn:li:share:1'], 201),
        ]);
        $this->postJson('/api/v1/crm/social-connectors', [
            'network' => 'instagram',
            'name' => 'Brand',
            'access_token' => 'ig-token',
            'external_id' => '1789',
            'enabled' => true,
        ])->assertCreated();
        $calendar = $this->postJson('/api/v1/crm/content-calendars', [
            'kind' => 'social',
            'network' => 'instagram',
            'name' => 'Feed',
        ])->assertCreated()->json('data.id');
        $item = $this->postJson('/api/v1/crm/content-calendars/'.$calendar.'/items', [
            'title' => 'Launch',
            'body' => 'Hello',
            'image_url' => 'https://cdn.example/launch.jpg',
            'status' => 'scheduled',
        ])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/crm/content-items/'.$item.'/publish')->assertOk()
            ->assertJsonPath('data.publish_status', 'published')
            ->assertJsonPath('data.external_post_id', 'ig-post-1');

        $this->getJson('/api/v1/core/health/modules')->assertOk()->assertJsonPath('data.status', 'ok');
        $this->assertNotNull(User::query()->find($user->id));
    }

    private function deal(User $user): CrmDeal
    {
        $pipeline = CrmPipeline::query()->create(['name' => 'Sales', 'is_active' => true, 'created_by' => $user->id]);
        $stage = CrmStage::query()->create([
            'pipeline_id' => $pipeline->id,
            'name' => 'New',
            'sort_order' => 1,
            'color' => '#000',
            'probability' => 10,
        ]);
        $account = CrmAccount::query()->create(['name' => 'Buyer '.uniqid(), 'type' => 'customer']);

        return CrmDeal::query()->create([
            'name' => 'Deal',
            'account_id' => $account->id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stage->id,
            'created_by' => $user->id,
        ]);
    }
}
