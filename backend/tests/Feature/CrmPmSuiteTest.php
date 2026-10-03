<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Database\Seeders\RolesAndPermissionsSeeder;
use Modules\Core\Entities\CoreNotification;
use Modules\Crm\Entities\CrmAccount;
use Modules\Crm\Entities\CrmContact;
use Modules\Crm\Entities\CrmDeal;
use Modules\Crm\Entities\CrmLead;
use Modules\Crm\Entities\CrmMessage;
use Modules\Crm\Entities\CrmPipeline;
use Modules\Crm\Entities\CrmStage;
use Modules\Crm\Entities\CrmStatus;
use Modules\Projects\Entities\PrjFile;
use Modules\Projects\Entities\Project;
use Modules\Projects\Entities\ProjectTask;
use Modules\Projects\Entities\TimeEntry;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class CrmPmSuiteTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->seedLicensedModules();
    }

    public function test_timeline_reminder_scoring_and_stage_automation(): void
    {
        $user = $this->actingAsRole(RolesAndPermissionsSeeder::ROLE_SYSTEM_MANAGER);
        Sanctum::actingAs($user);
        $status = CrmStatus::query()->create(['name' => 'New', 'color' => '#111', 'sort_order' => 1, 'is_active' => true]);
        $account = CrmAccount::query()->create(['name' => 'Northwind', 'type' => 'customer', 'created_by' => $user->id]);
        $pipeline = CrmPipeline::query()->create(['name' => 'Sales', 'is_active' => true, 'created_by' => $user->id]);
        $open = CrmStage::query()->create(['pipeline_id' => $pipeline->id, 'name' => 'Open', 'sort_order' => 1, 'color' => '#333', 'probability' => 20]);
        $won = CrmStage::query()->create(['pipeline_id' => $pipeline->id, 'name' => 'Won', 'sort_order' => 2, 'color' => '#0a0', 'probability' => 100, 'is_closed' => true, 'is_won' => true]);
        $deal = CrmDeal::query()->create([
            'name' => 'Rollout',
            'account_id' => $account->id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $open->id,
            'amount' => 2000,
            'probability' => 50,
            'assigned_to' => $user->id,
            'created_by' => $user->id,
        ]);

        $activity = $this->postJson('/api/v1/crm/timeline', [
            'type' => 'call',
            'subject' => 'Intro call',
            'description' => 'Talked pricing',
            'related_type' => 'account',
            'related_id' => $account->id,
            'remind_at' => now()->subMinute()->toIso8601String(),
        ]);
        $activity->assertCreated();
        $this->getJson('/api/v1/crm/timeline?account_id='.$account->id)
            ->assertOk()
            ->assertJsonPath('data.0.subject', 'Intro call');
        $this->postJson('/api/v1/crm/timeline/'.$activity->json('data.id').'/complete')->assertOk();
        $this->artisan('crm:dispatch-reminders')->assertSuccessful();

        $lead = CrmLead::query()->create([
            'topic' => 'Warm',
            'first_name' => 'Sara',
            'last_name' => 'Nouri',
            'mobile' => '09120000000',
            'email' => 'sara@example.com',
            'status_id' => $status->id,
            'created_by' => $user->id,
        ]);
        $this->postJson('/api/v1/crm/score-rules', [
            'name' => 'Has email',
            'kind' => 'field',
            'target' => 'email',
            'operator' => 'present',
            'weight' => 40,
        ])->assertCreated();
        $this->postJson('/api/v1/crm/leads/'.$lead->id.'/score')->assertOk();
        $this->assertGreaterThanOrEqual(40, (int) $lead->fresh()->lead_score);

        Http::fake(['https://hooks.example.test/*' => Http::response(['ok' => true], 200)]);
        $this->postJson('/api/v1/crm/automation-rules', [
            'name' => 'Won follow up',
            'trigger' => 'crm.deal.stage_changed',
            'conditions' => ['stage_id' => $won->id],
            'actions' => [
                ['type' => 'create-task', 'payload' => ['title' => 'Kickoff', 'assignee_id' => $user->id]],
                ['type' => 'webhook', 'payload' => ['url' => 'https://hooks.example.test/crm']],
            ],
        ])->assertCreated();

        $this->patchJson('/api/v1/crm/deals/'.$deal->id.'/move', ['stage_id' => $won->id])->assertOk();
        $this->assertDatabaseHas('prj_tasks', ['title' => 'Kickoff', 'assignee_id' => $user->id]);
        Http::assertSent(fn ($request) => $request->url() === 'https://hooks.example.test/crm');
    }

    public function test_outreach_forecast_merge_and_audiences(): void
    {
        $user = $this->actingAsRole(RolesAndPermissionsSeeder::ROLE_SALES_CONSULTANT);
        Sanctum::actingAs($user);
        $status = CrmStatus::query()->create(['name' => 'New', 'color' => '#111', 'sort_order' => 1, 'is_active' => true]);
        $lead = CrmLead::query()->create([
            'topic' => 'Nurture',
            'first_name' => 'Ali',
            'last_name' => 'Rad',
            'email' => 'ali@example.com',
            'mobile' => '09121111111',
            'status_id' => $status->id,
            'created_by' => $user->id,
        ]);
        $template = $this->postJson('/api/v1/crm/templates', [
            'channel' => 'email',
            'name' => 'Hello',
            'subject' => 'Hi {{first_name}}',
            'body' => 'Welcome {{name}}',
        ])->assertCreated();
        $this->postJson('/api/v1/crm/messages', [
            'channel' => 'sms',
            'related_type' => 'lead',
            'related_id' => $lead->id,
            'body' => 'Code {{first_name}}',
        ])->assertCreated()->assertJsonPath('data.status', 'logged');
        $this->assertDatabaseHas('crm_messages', ['channel' => 'sms', 'related_id' => $lead->id]);

        $sequence = $this->postJson('/api/v1/crm/sequences', [
            'name' => 'Welcome',
            'channel' => 'email',
            'steps' => [['template_id' => $template->json('data.id'), 'delay_days' => 0]],
        ])->assertCreated();
        $this->postJson('/api/v1/crm/sequences/'.$sequence->json('data.id').'/enroll', [
            'related_type' => 'lead',
            'related_id' => $lead->id,
        ])->assertCreated();
        $this->postJson('/api/v1/crm/sequences/run-due')->assertOk()->assertJsonPath('data.sent', 1);
        $this->assertTrue(CrmMessage::query()->where('channel', 'email')->where('related_id', $lead->id)->exists());

        $pipeline = CrmPipeline::query()->create(['name' => 'Main', 'is_active' => true, 'created_by' => $user->id]);
        $stage = CrmStage::query()->create(['pipeline_id' => $pipeline->id, 'name' => 'Talk', 'sort_order' => 1, 'color' => '#333', 'probability' => 50]);
        $primary = CrmAccount::query()->create(['name' => 'Acme', 'type' => 'customer', 'website' => 'acme.test']);
        $duplicate = CrmAccount::query()->create(['name' => 'Acme', 'type' => 'customer']);
        CrmContact::query()->create([
            'account_id' => $duplicate->id,
            'first_name' => 'Mina',
            'last_name' => 'Karimi',
            'email' => 'mina@acme.test',
            'created_by' => $user->id,
        ]);
        CrmDeal::query()->create([
            'name' => 'Open',
            'account_id' => $primary->id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stage->id,
            'amount' => 2000,
            'probability' => 50,
            'assigned_to' => $user->id,
            'created_by' => $user->id,
        ]);
        $this->postJson('/api/v1/crm/quotas', [
            'user_id' => $user->id,
            'period_start' => now()->startOfYear()->toDateString(),
            'period_end' => now()->endOfYear()->toDateString(),
            'amount' => 5000,
        ])->assertCreated();
        $this->getJson('/api/v1/crm/forecast')
            ->assertOk()
            ->assertJsonPath('data.weighted_amount', 1000);

        $this->getJson('/api/v1/crm/accounts/'.$primary->id.'/duplicates')->assertOk()->assertJsonPath('data.0.id', $duplicate->id);
        $this->postJson('/api/v1/crm/accounts/merge', [
            'primary_id' => $primary->id,
            'duplicate_id' => $duplicate->id,
        ])->assertOk();
        $this->assertDatabaseHas('crm_contacts', ['account_id' => $primary->id, 'email' => 'mina@acme.test']);
        $this->assertSoftDeleted('crm_accounts', ['id' => $duplicate->id]);

        $tag = $this->postJson('/api/v1/crm/tags', ['name' => 'vip', 'color' => '#f00'])->assertCreated();
        $this->postJson('/api/v1/crm/tags/'.$tag->json('data.id').'/attach', [
            'taggable_type' => 'account',
            'taggable_id' => $primary->id,
        ])->assertOk();
        $segment = $this->postJson('/api/v1/crm/segments', [
            'name' => 'VIP accounts',
            'entity' => 'account',
            'filters' => ['tag_id' => $tag->json('data.id')],
        ])->assertCreated();
        $this->getJson('/api/v1/crm/segments/'.$segment->json('data.id').'/preview')
            ->assertOk()
            ->assertJsonPath('data.count', 1);
        $list = $this->postJson('/api/v1/crm/lists', ['name' => 'Launch list'])->assertCreated();
        $this->postJson('/api/v1/crm/lists/'.$list->json('data.id').'/members', [
            'member_type' => 'account',
            'member_id' => $primary->id,
        ])->assertCreated();
        $this->postJson('/api/v1/crm/custom-fields', [
            'entity' => 'account',
            'key' => 'tax_code',
            'label' => 'Tax code',
            'type' => 'text',
        ])->assertCreated();
        $this->putJson('/api/v1/crm/custom-field-values', [
            'entity_type' => 'account',
            'entity_id' => $primary->id,
            'values' => ['tax_code' => '123'],
        ])->assertOk();
        $this->getJson('/api/v1/crm/contacts/export')->assertOk();
        $this->getJson('/api/v1/crm/audit')->assertOk();
        $this->getJson('/api/v1/core/search?q=Acme')->assertOk()->assertJsonPath('data.accounts.0.name', 'Acme');
    }

    public function test_pm_workload_budget_files_mentions_sla_and_portal_approval(): void
    {
        Storage::fake('public');
        $manager = $this->actingAsRole(RolesAndPermissionsSeeder::ROLE_PROJECT_MANAGER);
        $mate = User::factory()->create(['name' => 'Neda', 'is_active' => true]);
        Sanctum::actingAs($manager);

        $account = CrmAccount::query()->create(['name' => 'Client Co', 'type' => 'customer']);
        $template = Project::query()->create([
            'name' => 'Website template',
            'status' => 'active',
            'is_template' => true,
            'created_by' => $manager->id,
        ]);
        ProjectTask::query()->create([
            'project_id' => $template->id,
            'title' => 'Discovery',
            'status' => 'open',
            'estimate_hours' => 8,
            'created_by' => $manager->id,
        ]);
        $created = $this->postJson('/api/v1/projects/project-templates/'.$template->id.'/instantiate', [
            'name' => 'Client website',
            'customer_account_id' => $account->id,
        ])->assertCreated();
        $projectId = (int) $created->json('data.id');
        $this->assertFalse((bool) $created->json('data.is_template'));
        $this->assertDatabaseHas('prj_tasks', ['project_id' => $projectId, 'title' => 'Discovery']);

        $task = ProjectTask::query()->where('project_id', $projectId)->firstOrFail();
        $task->update([
            'assignee_id' => $mate->id,
            'due_at' => now()->subDay(),
            'estimate_hours' => 6,
        ]);
        $blocker = ProjectTask::query()->create([
            'project_id' => $projectId,
            'title' => 'Blocker',
            'status' => 'open',
            'starts_at' => now()->subDays(3),
            'due_at' => now()->addDay(),
            'created_by' => $manager->id,
        ]);
        $this->patchJson('/api/v1/projects/tasks/'.$task->id, [
            'dependencies' => [$blocker->id],
            'estimate_hours' => 6,
        ])->assertOk();
        $gantt = $this->getJson('/api/v1/projects/tasks/gantt?project_id='.$projectId)->assertOk();
        $row = collect($gantt->json('data'))->firstWhere('id', $task->id);
        $this->assertSame([$blocker->id], $row['depends_on']);

        $this->putJson('/api/v1/projects/projects/'.$projectId.'/budget', [
            'budget_amount' => 1000,
            'budget_hours' => 10,
            'hourly_rate' => 50,
        ])->assertOk()->assertJsonPath('data.budget_amount', '1000.00');
        TimeEntry::query()->create([
            'user_id' => $mate->id,
            'project_id' => $projectId,
            'task_id' => $task->id,
            'started_at' => now()->subHour(),
            'ended_at' => now(),
            'duration_seconds' => 3600,
            'is_running' => false,
        ]);
        $this->getJson('/api/v1/projects/projects/'.$projectId.'/budget')
            ->assertOk()
            ->assertJsonPath('data.actual_hours', 1)
            ->assertJsonPath('data.actual_cost', 50);

        $this->getJson('/api/v1/projects/workload')->assertOk();
        $this->getJson('/api/v1/projects/delay-alerts')->assertOk();
        $this->postJson('/api/v1/projects/delay-alerts/notify')->assertOk();
        $this->assertTrue(CoreNotification::query()->where('user_id', $mate->id)->where('type', 'pm.delay')->exists());

        $this->postJson('/api/v1/projects/tasks/'.$task->id.'/comments', ['body' => '@Neda please review'])->assertCreated();
        $this->assertTrue(CoreNotification::query()->where('user_id', $mate->id)->where('type', 'pm.mention')->exists());

        $upload = $this->post('/api/v1/projects/files', [
            'project_id' => $projectId,
            'shared_with_client' => 1,
            'file' => UploadedFile::fake()->create('brief.pdf', 20, 'application/pdf'),
        ]);
        $upload->assertCreated();
        $fileId = (int) $upload->json('data.id');
        $version = $this->post('/api/v1/projects/files/'.$fileId.'/versions', [
            'file' => UploadedFile::fake()->create('brief-v2.pdf', 22, 'application/pdf'),
        ]);
        $version->assertCreated()->assertJsonPath('data.version', 2);
        $this->assertSame($fileId, (int) PrjFile::query()->find($version->json('data.id'))->family_id);

        $ticket = $this->postJson('/api/v1/projects/tickets', [
            'subject' => 'Login fails',
            'priority' => 'urgent',
            'customer_account_id' => $account->id,
            'project_id' => $projectId,
        ])->assertCreated();
        $this->assertNotNull($ticket->json('data.sla_first_due_at'));
        $this->postJson('/api/v1/projects/tickets/'.$ticket->json('data.id').'/replies', ['body' => 'Looking now'])->assertCreated();
        $this->getJson('/api/v1/projects/tickets/sla')->assertOk();

        $approval = $this->postJson('/api/v1/projects/approvals', [
            'project_id' => $projectId,
            'customer_account_id' => $account->id,
            'title' => 'Homepage',
        ])->assertCreated();

        $admin = $this->actingAsRole(RolesAndPermissionsSeeder::ROLE_SYSTEM_MANAGER);
        Sanctum::actingAs($admin);
        $grant = $this->postJson('/api/v1/crm/accounts/'.$account->id.'/portal-access', [
            'email' => 'client@example.com',
            'name' => 'Client User',
            'password' => 'portal-pass-1',
        ])->assertCreated();
        $client = User::query()->findOrFail($grant->json('data.user_id'));
        Sanctum::actingAs($client);
        $summary = $this->getJson('/api/v1/projects/portal/summary')->assertOk();
        $this->assertNotEmpty($summary->json('data.files'));
        $this->assertNotEmpty($summary->json('data.approvals'));
        $this->postJson('/api/v1/projects/portal/approvals/'.$approval->json('data.id').'/decide', [
            'status' => 'approved',
            'decision_note' => 'Looks good',
        ])->assertOk()->assertJsonPath('data.status', 'approved');

        Sanctum::actingAs($manager);
        $this->putJson('/api/v1/projects/connectors/slack', [
            'label' => 'Team chat',
            'status' => 'stub',
        ])->assertOk();
        $this->postJson('/api/v1/projects/connectors/slack/test')->assertOk()->assertJsonPath('data.status', 'stub');
        $this->putJson('/api/v1/core/dashboard/widgets', [
            'widgets' => [
                ['key' => 'forecast', 'visible' => true, 'sort_order' => 1],
                ['key' => 'delays', 'visible' => false, 'sort_order' => 2],
            ],
        ])->assertOk();
        $this->getJson('/api/v1/projects/audit')->assertOk();
    }
}
