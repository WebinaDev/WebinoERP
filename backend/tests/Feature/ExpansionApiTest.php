<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Entities\CoreNotification;
use Modules\Core\Entities\SystemModule;
use Modules\Crm\Entities\CrmAccount;
use Modules\Crm\Entities\CrmContact;
use Modules\Crm\Entities\CrmDeal;
use Modules\Crm\Entities\CrmPipeline;
use Modules\Crm\Entities\CrmStage;
use Modules\Integrations\Entities\CalendarAccount;
use Modules\Integrations\Entities\ChannelDelivery;
use Modules\Integrations\Entities\ChatBridge;
use Modules\Integrations\Entities\IntegrationSetting;
use Modules\Projects\Entities\PrjKanbanBoard;
use Modules\Projects\Entities\PrjKanbanCard;
use Modules\Projects\Entities\PrjKanbanColumn;
use Modules\Projects\Entities\Project;
use Modules\Projects\Entities\ProjectTask;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class ExpansionApiTest extends TestCase
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

    public function test_google_calendar_pulls_appointment_and_pushes_task(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);
        Http::fake(function ($request) {
            $url = $request->url();
            if ($request->method() === 'GET' && str_contains($url, '/events')) {
                return Http::response(['items' => [[
                    'id' => 'evt-1',
                    'status' => 'confirmed',
                    'summary' => 'Kickoff',
                    'start' => ['dateTime' => '2026-10-04T09:00:00+03:30'],
                    'end' => ['dateTime' => '2026-10-04T10:00:00+03:30'],
                    'extendedProperties' => ['private' => ['webina_kind' => 'appointment']],
                ]]]);
            }
            if ($request->method() === 'POST' && str_contains($url, '/events') && ! str_contains($url, 'watch')) {
                return Http::response(['id' => 'evt-task']);
            }

            return Http::response(['id' => 'chan-1']);
        });

        $account = CalendarAccount::query()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'access_token' => 'token',
            'expires_at' => now()->addHour(),
            'calendar_id' => 'primary',
            'status' => 'active',
        ]);

        $this->postJson('/api/v1/integrations/calendars/'.$account->id.'/sync')->assertOk()
            ->assertJsonPath('data.pulled', 1);
        $this->assertDatabaseHas('prj_appointments', ['title' => 'Kickoff']);

        ProjectTask::query()->create([
            'title' => 'Follow up',
            'status' => 'open',
            'due_at' => now()->addDay(),
            'start_at' => now()->addDay()->subHour(),
            'created_by' => $user->id,
        ]);
        $this->postJson('/api/v1/integrations/calendars/'.$account->id.'/sync')->assertOk();
        $this->assertDatabaseHas('int_calendar_links', ['local_type' => 'task']);
    }

    public function test_slack_inbound_command_creates_task_and_sms_gate(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);
        Http::fake([
            'https://hooks.slack.com/*' => Http::response('ok', 200),
            'https://edge.ippanel.com/*' => Http::response(['data' => ['id' => 9], 'meta' => ['status' => true]], 200),
        ]);

        ChatBridge::query()->create([
            'provider' => 'slack',
            'name' => 'Ops',
            'bot_token' => 'https://hooks.slack.com/services/T/B/X',
            'channel_id' => 'C1',
            'inbound_commands' => true,
            'enabled' => true,
        ]);

        $this->postJson('/api/v1/integrations/bridges/webhook/slack', [
            'type' => 'event_callback',
            'event' => ['type' => 'message', 'text' => '/task Buy milk', 'channel' => 'C1', 'ts' => '1'],
        ])->assertOk();
        $this->assertDatabaseHas('prj_tasks', ['title' => 'Buy milk']);

        $this->postJson('/api/v1/integrations/bridges/webhook/slack', [
            'type' => 'url_verification',
            'challenge' => 'abc123',
        ])->assertOk()->assertJsonPath('challenge', 'abc123');

        IntegrationSetting::putString('sms_policy', 'mode', 'highest_only');
        IntegrationSetting::putString('sms_policy', 'floor', 'high');
        IntegrationSetting::putString('modirpayamak', 'enabled', '1');
        IntegrationSetting::putString('modirpayamak', 'api_key', 'test-key');

        $this->postJson('/api/v1/integrations/notifications/dispatch', [
            'title' => 'Urgent',
            'body' => 'Deal won',
            'priority' => 'urgent',
            'event_key' => 'deal.won',
            'phone' => '09120000000',
        ])->assertOk();
        $this->assertDatabaseHas('int_channel_deliveries', ['channel' => 'sms', 'status' => 'sent']);

        $this->postJson('/api/v1/integrations/notifications/dispatch', [
            'title' => 'Note',
            'body' => 'Low',
            'priority' => 'normal',
            'event_key' => 'deal.note',
            'phone' => '09120000000',
        ])->assertOk();
        $this->assertDatabaseHas('int_channel_deliveries', ['channel' => 'sms', 'status' => 'suppressed']);
        $this->assertTrue(CoreNotification::query()->where('user_id', $user->id)->exists());
        $this->assertTrue(ChannelDelivery::query()->where('channel', 'in_app')->exists());
    }

    public function test_mailbox_links_contact_and_crm_activity(): void
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
        $box = $this->postJson('/api/v1/integrations/mailbox', [
            'provider' => 'imap',
            'email' => 'sales@webino.local',
        ])->assertCreated()->json('data.id');

        $this->postJson('/api/v1/integrations/mailbox/'.$box.'/import', [
            'from_email' => 'sara@example.com',
            'to_email' => 'sales@webino.local',
            'subject' => 'Proposal',
            'body' => 'Please review',
            'thread_key' => 'thread-1',
            'external_id' => 'm1',
        ])->assertCreated();

        $this->assertDatabaseHas('int_email_messages', [
            'subject' => 'Proposal',
            'crm_account_id' => $account->id,
        ]);
        $this->assertDatabaseHas('crm_activities', ['type' => 'email', 'related_id' => $account->id]);

        $threads = $this->getJson('/api/v1/integrations/mailbox/'.$box.'/threads')->assertOk()->json('data');
        $this->assertSame('thread-1', $threads[0]['thread_key']);
    }

    public function test_crm_quote_currency_form_esign_and_einvoice(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $company = $this->postJson('/api/v1/crm/companies', [
            'name' => 'Webina',
            'legal_name' => 'Webina LLC',
            'national_id' => '10101010101',
            'economic_code' => '411111111111',
            'is_default' => true,
        ])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/crm/companies/'.$company.'/switch')->assertOk()
            ->assertJsonPath('data.company_id', $company);

        $this->postJson('/api/v1/crm/fx-rates', [
            'base_code' => 'USD',
            'quote_code' => 'IRR',
            'rate' => 600000,
        ])->assertCreated();
        $this->getJson('/api/v1/crm/fx/convert?amount=2&from=USD&to=IRR')->assertOk()
            ->assertJsonPath('data.amount', 1200000);

        $product = $this->postJson('/api/v1/crm/catalog-products', [
            'name' => 'Website',
            'tax_percent' => 10,
        ])->assertCreated()->json('data.id');
        $book = $this->postJson('/api/v1/crm/price-books', [
            'name' => '1405',
            'currency_code' => 'IRR',
            'items' => [
                ['product_id' => $product, 'min_qty' => 1, 'unit_price' => 1000, 'discount_percent' => 10],
            ],
        ])->assertCreated()->json('data.id');

        $pipeline = CrmPipeline::query()->create(['name' => 'Sales', 'is_active' => true, 'created_by' => $user->id]);
        $stage = CrmStage::query()->create(['pipeline_id' => $pipeline->id, 'name' => 'New', 'sort_order' => 1, 'color' => '#000', 'probability' => 10]);
        $crmAccount = CrmAccount::query()->create(['name' => 'Buyer', 'type' => 'customer']);
        $deal = CrmDeal::query()->create([
            'name' => 'Site',
            'account_id' => $crmAccount->id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stage->id,
            'created_by' => $user->id,
        ]);
        $quote = $this->postJson('/api/v1/crm/deals/'.$deal->id.'/quote', [
            'price_book_id' => $book,
            'currency' => 'IRR',
            'lines' => [['product_id' => $product, 'qty' => 2]],
        ])->assertOk()->json('data');
        $this->assertEquals(1800, (float) $quote['subtotal'] - (float) $quote['discount']);
        $this->assertEquals(1980, (float) $quote['total']);

        $slug = $this->postJson('/api/v1/crm/lead-forms', [
            'name' => 'Landing',
            'slug' => 'landing',
            'notify_user_id' => $user->id,
        ])->assertCreated()->json('data.slug');
        $this->postJson('/api/v1/crm/public/forms/'.$slug, [
            'first_name' => 'Neda',
            'last_name' => 'Karimi',
            'mobile' => '09121111111',
            'utm_source' => 'instagram',
            'utm_campaign' => 'spring',
            'landing_path' => '/lp/spring',
        ])->assertCreated();
        $this->assertDatabaseHas('crm_attributions', ['utm_campaign' => 'spring', 'landing_path' => '/lp/spring']);

        $ai = $this->postJson('/api/v1/crm/ai/assist', [
            'purpose' => 'deal_risk',
            'context' => ['amount' => 900000000, 'probability' => 10, 'age_days' => 45],
        ])->assertOk()->json('data');
        $this->assertSame('heuristic', $ai['source']);
        $this->assertSame('high', $ai['structured']['level']);

        $envelope = $this->postJson('/api/v1/crm/esign', [
            'title' => 'Contract',
            'body' => 'Terms',
            'signers' => [['name' => 'Neda', 'national_id' => '0012345678', 'mobile' => '09120000000']],
        ])->assertCreated()->json('data');
        $signerId = $envelope['signers'][0]['id'];
        $code = $this->postJson('/api/v1/crm/esign/'.$envelope['id'].'/signers/'.$signerId.'/otp')->assertOk()->json('data.debug_code');
        $this->postJson('/api/v1/crm/esign/'.$envelope['id'].'/signers/'.$signerId.'/sign', ['code' => $code])
            ->assertOk()->assertJsonPath('data.envelope_status', 'signed');
        $this->getJson('/api/v1/crm/esign')->assertOk()->assertJsonPath('data.0.status', 'signed');

        $invoice = $this->postJson('/api/v1/crm/einvoices', [
            'company_id' => $company,
            'buyer_type' => 'legal',
            'buyer_national_id' => '10888888888',
            'buyer_economic_code' => '422222222222',
            'items' => [[
                'description' => 'Design',
                'qty' => 1,
                'fee' => 1000000,
                'vat_rate' => 10,
                'commodity_code' => '2720000000000',
            ]],
        ])->assertCreated()->json('data');
        $this->assertNotEmpty($invoice['document']['taxid']);
        $this->assertSame('411111111111', $invoice['document']['seller']['economic_code']);
        $this->postJson('/api/v1/crm/einvoices/'.$invoice['id'].'/submit')->assertOk()
            ->assertJsonPath('data.status', 'queued');
        $this->getJson('/api/v1/crm/einvoices')->assertOk()->assertJsonPath('data.0.id', $invoice['id']);
    }

    public function test_content_calendar_reminder_and_pm_planning(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);
        $account = CrmAccount::query()->create(['name' => 'Agency client', 'type' => 'customer']);
        $calendar = $this->postJson('/api/v1/crm/content-calendars', [
            'account_id' => $account->id,
            'kind' => 'social',
            'network' => 'instagram',
            'name' => 'Spring',
        ])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/crm/content-calendars/'.$calendar.'/items', [
            'title' => 'Launch post',
            'status' => 'scheduled',
            'assignee_id' => $user->id,
            'publish_start' => now()->addDay()->toIso8601String(),
            'publish_end' => now()->addDay()->addHour()->toIso8601String(),
            'remind_at' => now()->subMinute()->toIso8601String(),
        ])->assertCreated();
        $this->getJson('/api/v1/crm/content-calendars')->assertOk()->assertJsonPath('data.0.items.0.title', 'Launch post');
        $this->postJson('/api/v1/crm/content-items/remind-due')->assertOk()->assertJsonPath('data.count', 1);
        $this->postJson('/api/v1/crm/ai/assist', [
            'purpose' => 'content_brief',
            'context' => ['title' => 'Launch post', 'network' => 'instagram'],
        ])->assertOk()->assertJsonPath('data.structured.network', 'instagram');

        $project = Project::query()->create(['name' => 'Build', 'status' => 'open', 'created_by' => $user->id]);
        $board = PrjKanbanBoard::query()->create([
            'owner_type' => Project::class,
            'owner_id' => $project->id,
            'name' => 'Board',
            'meta' => [],
        ]);
        $column = PrjKanbanColumn::query()->create([
            'board_id' => $board->id,
            'name' => 'Doing',
            'sort_order' => 1,
            'wip_limit' => 1,
        ]);
        $this->postJson('/api/v1/projects/kanban/cards', [
            'board_id' => $board->id,
            'column_id' => $column->id,
            'title' => 'One',
            'swimlane_key' => 'design',
        ])->assertCreated();
        $this->postJson('/api/v1/projects/kanban/cards', [
            'board_id' => $board->id,
            'column_id' => $column->id,
            'title' => 'Two',
        ])->assertStatus(422);
        $this->assertSame('design', PrjKanbanCard::query()->first()->swimlane_key);
        $this->patchJson('/api/v1/projects/kanban/boards/'.$board->id, ['swimlane_field' => 'custom'])->assertOk();

        $path = $this->postJson('/api/v1/projects/planning/critical-path', [
            'tasks' => [
                ['id' => 1, 'title' => 'A', 'duration_days' => 2],
                ['id' => 2, 'title' => 'B', 'duration_days' => 3],
            ],
            'links' => [['source_id' => 1, 'target_id' => 2, 'type' => 'finish_to_start']],
        ])->assertOk()->json('data');
        $this->assertSame([1, 2], $path['critical_ids']);
        $this->postJson('/api/v1/projects/planning/baselines', ['name' => 'Baseline 1', 'project_id' => $project->id])->assertCreated();

        $today = now()->toDateString();
        $this->postJson('/api/v1/projects/planning/leave', [
            'user_id' => $user->id,
            'starts_on' => $today,
            'ends_on' => $today,
            'kind' => 'leave',
        ])->assertCreated();
        $row = $this->getJson('/api/v1/projects/planning/capacity?user_id='.$user->id.'&from='.$today.'&to='.$today)
            ->assertOk()->json('data.0');
        $this->assertSame(0, (int) $row['available_hours']);

        $this->postJson('/api/v1/projects/offline/ops', [
            'ops' => [['client_id' => 'c1', 'action' => 'create_task', 'payload' => ['title' => 'Offline', 'project_id' => $project->id]]],
        ])->assertOk();
        $this->postJson('/api/v1/projects/offline/ops', [
            'ops' => [['client_id' => 'c1', 'action' => 'create_task', 'payload' => ['title' => 'Offline again']]],
        ])->assertOk()->assertJsonPath('data.0.replayed', true);
        $this->assertSame(1, ProjectTask::query()->where('title', 'Offline')->count());
    }

    public function test_studio_workflow_report_flags_sso_and_scim(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $workflow = $this->postJson('/api/v1/core/workflows', [
            'name' => 'Ping',
            'status' => 'published',
            'graph' => [
                'nodes' => [
                    ['id' => 't', 'type' => 'trigger'],
                    ['id' => 'n', 'type' => 'notify', 'config' => ['title' => 'Hello', 'body' => 'From workflow']],
                ],
                'edges' => [['from' => 't', 'to' => 'n']],
            ],
        ])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/core/workflows/'.$workflow.'/run', ['context' => ['user_id' => $user->id]])->assertOk();
        $this->assertDatabaseHas('core_notifications', ['user_id' => $user->id, 'type' => 'workflow']);

        $report = $this->postJson('/api/v1/core/bi-reports', [
            'name' => 'Deals',
            'source' => 'deals',
            'columns' => ['id', 'name', 'amount'],
            'layout' => ['chart' => 'bar', 'x' => 'name', 'y' => 'amount'],
        ])->assertCreated()->json('data.id');
        $this->getJson('/api/v1/core/bi-reports/'.$report.'/run')->assertOk();
        $this->get('/api/v1/core/bi-reports/'.$report.'/export.csv')->assertOk();

        $this->postJson('/api/v1/core/flags', [
            'key' => 'new_cpq',
            'enabled' => true,
            'rollout_percent' => 100,
            'sandbox_only' => true,
        ])->assertOk();
        $this->getJson('/api/v1/core/flags/evaluate?keys[]=new_cpq')->assertOk()->assertJsonPath('data.flags.new_cpq', false);
        $this->withHeader('X-Webino-Sandbox', '1')->getJson('/api/v1/core/flags/evaluate?keys[]=new_cpq')
            ->assertOk()->assertJsonPath('data.flags.new_cpq', true);

        Http::fake([
            'https://idp.example.com/token' => Http::response(['access_token' => 'oidc-token']),
            'https://idp.example.com/userinfo' => Http::response(['email' => 'oidc@example.com', 'name' => 'OIDC User']),
        ]);
        $provider = $this->postJson('/api/v1/core/sso', [
            'name' => 'Corp',
            'protocol' => 'oidc',
            'enabled' => true,
            'client_id' => 'cid',
            'client_secret' => 'sec',
            'issuer' => 'https://idp.example.com',
        ])->assertCreated()->json('data.id');
        $url = $this->getJson('/api/v1/core/sso/oidc/'.$provider.'/redirect')->assertOk()->json('data.url');
        $this->assertStringContainsString('https://idp.example.com/authorize', $url);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->getJson('/api/v1/core/sso/oidc/callback?code=abc&state='.$query['state'])
            ->assertOk()
            ->assertJsonPath('data.user.email', 'oidc@example.com');

        $saml = $this->postJson('/api/v1/core/sso', [
            'name' => 'SAML',
            'protocol' => 'saml',
            'enabled' => true,
            'client_secret' => 'shared-secret',
        ])->assertCreated()->json('data.id');
        $this->get('/api/v1/core/sso/saml/'.$saml.'/metadata')->assertOk()->assertSee('AssertionConsumerService', false);
        $xml = '<Response xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion"><saml:NameID>saml-user@example.com</saml:NameID><saml:Attribute Name="secret"><saml:AttributeValue>shared-secret</saml:AttributeValue></saml:Attribute></Response>';
        $this->postJson('/api/v1/core/sso/saml/'.$saml.'/acs', ['SAMLResponse' => base64_encode($xml)])
            ->assertOk()
            ->assertJsonPath('data.email', 'saml-user@example.com');

        $token = $this->postJson('/api/v1/core/sso/scim-tokens', ['name' => 'okta'])->assertCreated()->json('data.token');
        $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/v1/core/scim/v2/Users', [
            'userName' => 'scim@example.com',
            'name' => ['givenName' => 'Scim', 'familyName' => 'User'],
            'active' => true,
        ])->assertCreated()->assertJsonPath('userName', 'scim@example.com');
        $this->assertTrue(User::query()->where('email', 'scim@example.com')->exists());
    }
}
