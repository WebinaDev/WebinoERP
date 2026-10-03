<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Database\Seeders\RolesAndPermissionsSeeder;
use Modules\Crm\Entities\CrmAccount;
use Modules\Crm\Entities\CrmActivity;
use Modules\Crm\Entities\CrmConsultation;
use Modules\Crm\Entities\CrmDeal;
use Modules\Crm\Entities\CrmPipeline;
use Modules\Crm\Entities\CrmStage;
use Modules\Crm\Entities\ConsultationStatus;
use Modules\Projects\Entities\Contract;
use Modules\Projects\Entities\PrjApproval;
use Modules\Projects\Entities\PrjAppointment;
use Modules\Projects\Entities\PrjTicket;
use Modules\Projects\Entities\ProInvoice;
use Modules\Projects\Entities\Project;
use Modules\Projects\Entities\ProjectTask;
use Modules\Sales\Entities\SalesInvoice;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class CrmPmPortalTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->seedLicensedModules();
    }

    public function test_portal_customer_sees_only_linked_projects_and_site(): void
    {
        $manager = $this->actingAsRole(RolesAndPermissionsSeeder::ROLE_SYSTEM_MANAGER);
        $own = CrmAccount::query()->create(['name' => 'Own Co', 'type' => 'customer']);
        $other = CrmAccount::query()->create(['name' => 'Other Co', 'type' => 'customer']);

        Sanctum::actingAs($manager);
        $grant = $this->postJson('/api/v1/crm/accounts/'.$own->id.'/portal-access', [
            'email' => 'customer@example.com',
            'name' => 'Customer One',
            'password' => 'portal-pass-1',
        ]);
        $grant->assertCreated();
        $client = User::query()->findOrFail($grant->json('data.user_id'));

        $visible = Project::query()->create([
            'name' => 'Own site build',
            'status' => 'active',
            'customer_account_id' => $own->id,
            'created_by' => $manager->id,
        ]);
        ProjectTask::query()->create([
            'project_id' => $visible->id,
            'title' => 'Done work',
            'status' => 'done',
            'created_by' => $manager->id,
        ]);
        $hidden = Project::query()->create([
            'name' => 'Someone else',
            'status' => 'active',
            'customer_account_id' => $other->id,
            'created_by' => $manager->id,
        ]);
        WebinoSiteProvision::query()->create([
            'crm_account_id' => $own->id,
            'slug' => 'own-co',
            'domain' => 'own.example.com',
            'status' => 'ready',
        ]);

        Sanctum::actingAs($client);
        $list = $this->getJson('/api/v1/projects/projects');
        $list->assertOk();
        $names = collect($list->json('data'))->pluck('name')->all();
        $this->assertSame(['Own site build'], $names);
        $this->assertSame(100, $list->json('data.0.progress_percent'));

        $this->getJson('/api/v1/projects/projects/'.$hidden->id)->assertNotFound();
        $this->getJson('/api/v1/projects/projects/'.$visible->id)
            ->assertOk()
            ->assertJsonPath('data.progress_percent', 100);

        $summary = $this->getJson('/api/v1/projects/portal/summary');
        $summary->assertOk()
            ->assertJsonPath('data.account.id', $own->id)
            ->assertJsonPath('data.sites.0.domain', 'own.example.com')
            ->assertJsonPath('data.projects.0.id', $visible->id);

        Sanctum::actingAs($manager);
        $this->getJson('/api/v1/projects/portal/summary')->assertForbidden();
    }

    public function test_staff_roles_are_separated(): void
    {
        $crm = $this->actingAsRole(RolesAndPermissionsSeeder::ROLE_CRM_SPECIALIST);
        Sanctum::actingAs($crm);
        $this->getJson('/api/v1/crm/leads')->assertOk();
        $this->getJson('/api/v1/accounting/journals')->assertForbidden();

        $pm = $this->actingAsRole(RolesAndPermissionsSeeder::ROLE_PROJECT_MANAGER);
        Sanctum::actingAs($pm);
        $this->postJson('/api/v1/projects/projects', [
            'name' => 'Managed build',
            'status' => 'active',
        ])->assertCreated();
        $this->getJson('/api/v1/crm/leads')->assertForbidden();
    }

    public function test_appointment_end_must_follow_start(): void
    {
        Sanctum::actingAs($this->actingAsRole(RolesAndPermissionsSeeder::ROLE_PROJECT_MANAGER));

        $this->postJson('/api/v1/projects/appointments', [
            'title' => 'Consult',
            'starts_at' => '2026-04-02 10:00:00',
            'ends_at' => '2026-04-02 09:00:00',
        ])->assertStatus(422);
    }

    public function test_ticket_status_machine(): void
    {
        $user = $this->actingAsRole(RolesAndPermissionsSeeder::ROLE_PROJECT_MANAGER);
        Sanctum::actingAs($user);
        $ticket = PrjTicket::query()->create([
            'subject' => 'Need access',
            'status' => 'open',
            'priority' => 'normal',
        ]);

        $this->patchJson('/api/v1/projects/tickets/'.$ticket->id, [
            'status' => 'not-a-status',
        ])->assertStatus(422);

        $this->patchJson('/api/v1/projects/tickets/'.$ticket->id, [
            'status' => 'resolved',
        ])->assertOk()->assertJsonPath('data.status', 'resolved');
    }

    public function test_lost_deal_requires_loss_reason(): void
    {
        $user = $this->actingAsRole(RolesAndPermissionsSeeder::ROLE_CRM_SPECIALIST);
        Sanctum::actingAs($user);
        $pipeline = CrmPipeline::query()->create(['name' => 'Sales', 'is_active' => true, 'created_by' => $user->id]);
        $open = CrmStage::query()->create([
            'pipeline_id' => $pipeline->id,
            'name' => 'New',
            'sort_order' => 1,
            'probability' => 10,
            'color' => '#64748b',
        ]);
        $lost = CrmStage::query()->create([
            'pipeline_id' => $pipeline->id,
            'name' => 'Lost',
            'sort_order' => 2,
            'probability' => 0,
            'color' => '#b91c1c',
            'is_closed' => true,
            'is_won' => false,
        ]);
        $account = CrmAccount::query()->create(['name' => 'ACME', 'type' => 'customer']);
        $deal = CrmDeal::query()->create([
            'name' => 'Renewal',
            'account_id' => $account->id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $open->id,
            'created_by' => $user->id,
        ]);

        $this->patchJson('/api/v1/crm/deals/'.$deal->id.'/move', [
            'stage_id' => $lost->id,
        ])->assertStatus(422);

        $this->patchJson('/api/v1/crm/deals/'.$deal->id.'/move', [
            'stage_id' => $lost->id,
            'loss_reason' => 'Budget cut',
        ])->assertOk()->assertJsonPath('data.loss_reason', 'Budget cut');
    }

    public function test_project_export_appends_jalali_display_column(): void
    {
        $user = $this->actingAsRole(RolesAndPermissionsSeeder::ROLE_SYSTEM_MANAGER);
        Sanctum::actingAs($user);
        $project = Project::query()->create([
            'name' => 'Dated',
            'status' => 'active',
            'created_by' => $user->id,
        ]);
        $project->forceFill(['created_at' => '2026-03-21 00:00:00'])->save();

        $response = $this->get('/api/v1/projects/projects/export?locale=fa');
        $response->assertOk();
        $this->assertStringContainsString('۱۴۰۵', $response->streamedContent());
    }

    public function test_project_list_includes_contract_and_site_builder(): void
    {
        $user = $this->actingAsRole(RolesAndPermissionsSeeder::ROLE_SYSTEM_MANAGER);
        Sanctum::actingAs($user);
        $account = CrmAccount::query()->create(['name' => 'Site Co', 'type' => 'customer']);
        $project = Project::query()->create([
            'name' => 'Linked',
            'status' => 'active',
            'customer_account_id' => $account->id,
            'created_by' => $user->id,
        ]);
        $contract = Contract::query()->create([
            'title' => 'MSA',
            'project_id' => $project->id,
            'status' => 'active',
            'amount' => 1000,
            'created_by' => $user->id,
        ]);
        $site = WebinoSiteProvision::query()->create([
            'crm_account_id' => $account->id,
            'slug' => 'site-co',
            'domain' => 'site-co.example',
            'status' => 'ready',
        ]);

        $this->getJson('/api/v1/projects/projects')
            ->assertOk()
            ->assertJsonPath('data.0.contracts.0.id', $contract->id)
            ->assertJsonPath('data.0.sites.0.builder_path', 'admin/platform/sites/'.$site->id);

        $this->getJson('/api/v1/projects/projects/'.$project->id.'/details')
            ->assertOk()
            ->assertJsonPath('data.contracts.0.id', $contract->id)
            ->assertJsonPath('data.sites.0.builder_path', 'admin/platform/sites/'.$site->id);
    }

    public function test_dashboard_crm_pm_groups_todos(): void
    {
        $user = $this->actingAsRole(RolesAndPermissionsSeeder::ROLE_SYSTEM_MANAGER);
        Sanctum::actingAs($user);
        $project = Project::query()->create([
            'name' => 'Todos',
            'status' => 'active',
            'created_by' => $user->id,
        ]);
        ProjectTask::query()->create([
            'project_id' => $project->id,
            'title' => 'Late',
            'status' => 'open',
            'assignee_id' => $user->id,
            'due_at' => now()->subDay(),
            'created_by' => $user->id,
        ]);
        ProjectTask::query()->create([
            'project_id' => $project->id,
            'title' => 'Today',
            'status' => 'open',
            'assignee_id' => $user->id,
            'due_at' => now(),
            'created_by' => $user->id,
        ]);

        $this->getJson('/api/v1/core/dashboard/crm-pm')
            ->assertOk()
            ->assertJsonPath('data.todos.counts.overdue', 1)
            ->assertJsonPath('data.todos.counts.today', 1)
            ->assertJsonPath('data.todos.counts.mine', 2)
            ->assertJsonPath('data.pm.open_tasks', 2);
    }

    public function test_project_plan_deal_fields_and_consultation_statuses(): void
    {
        $user = $this->actingAsRole(RolesAndPermissionsSeeder::ROLE_SYSTEM_MANAGER);
        Sanctum::actingAs($user);
        $account = CrmAccount::query()->create(['name' => 'Plan Co', 'type' => 'customer']);
        $project = Project::query()->create([
            'name' => 'Planned',
            'status' => 'active',
            'customer_account_id' => $account->id,
            'created_by' => $user->id,
        ]);

        $milestone = $this->postJson('/api/v1/projects/projects/'.$project->id.'/milestones', [
            'title' => 'Launch',
            'due_date' => '2026-04-01',
        ]);
        $milestone->assertCreated()->assertJsonPath('data.title', 'Launch');
        $this->patchJson('/api/v1/projects/milestones/'.$milestone->json('data.id'), [
            'status' => 'done',
        ])->assertOk()->assertJsonPath('data.status', 'done');

        $sprint = $this->postJson('/api/v1/projects/sprints', [
            'project_id' => $project->id,
            'name' => 'Sprint 1',
            'starts_at' => '2026-04-01',
            'ends_at' => '2026-04-14',
        ]);
        $sprint->assertCreated();

        $epic = $this->postJson('/api/v1/projects/epics', [
            'project_id' => $project->id,
            'title' => 'Billing',
        ]);
        $epic->assertCreated()->assertJsonPath('data.title', 'Billing');
        $this->getJson('/api/v1/projects/epics?project_id='.$project->id)
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Billing');

        $first = ProjectTask::query()->create([
            'project_id' => $project->id,
            'title' => 'Design',
            'status' => 'open',
            'created_by' => $user->id,
        ]);
        $second = ProjectTask::query()->create([
            'project_id' => $project->id,
            'title' => 'Build',
            'status' => 'open',
            'created_by' => $user->id,
        ]);
        $this->patchJson('/api/v1/projects/tasks/'.$second->id, [
            'recurrence' => ['repeat' => 'daily'],
            'dependencies' => [$first->id],
        ])->assertOk()->assertJsonPath('data.recurrence.repeat', 'daily');
        $this->getJson('/api/v1/projects/tasks/'.$second->id)
            ->assertOk()
            ->assertJsonPath('data.dependencies.0.id', $first->id);

        $ticket = PrjTicket::query()->create([
            'subject' => 'Need logo',
            'status' => 'open',
            'project_id' => $project->id,
        ]);
        ProInvoice::query()->create([
            'number' => 'PRJ-1',
            'status' => 'draft',
            'total' => 250,
            'project_id' => $project->id,
            'created_by' => $user->id,
        ]);
        SalesInvoice::query()->create([
            'number' => 'SAL-1',
            'customer_name' => 'Plan Co',
            'total' => 400,
            'status' => 'draft',
            'project_id' => $project->id,
            'created_by' => $user->id,
        ]);
        $appointment = PrjAppointment::query()->create([
            'title' => 'Kickoff',
            'starts_at' => '2026-04-02 10:00:00',
            'status' => 'scheduled',
            'customer_account_id' => $account->id,
            'created_by' => $user->id,
        ]);

        $list = $this->getJson('/api/v1/projects/projects');
        $list->assertOk()
            ->assertJsonPath('data.0.tickets.0.path', 'crm/tickets?ticket_id='.$ticket->id)
            ->assertJsonPath('data.0.appointments.0.path', 'pm/appointments?appointment_id='.$appointment->id);
        $paths = collect($list->json('data.0.invoices'))->pluck('path')->all();
        $this->assertTrue(collect($paths)->contains(fn ($path) => str_contains((string) $path, 'invoice_id=')));

        $this->assertTrue(ConsultationStatus::query()->where('name', 'new')->exists());
        $this->getJson('/api/v1/crm/consultation-statuses')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'new');
    }

    public function test_personal_todos_include_due_work_and_problem_sites(): void
    {
        $user = $this->actingAsRole(RolesAndPermissionsSeeder::ROLE_SYSTEM_MANAGER);
        Sanctum::actingAs($user);
        $account = CrmAccount::query()->create(['name' => 'Follow Co', 'type' => 'customer']);
        $pipeline = CrmPipeline::query()->create(['name' => 'Sales', 'is_active' => true, 'created_by' => $user->id]);
        $stage = CrmStage::query()->create([
            'pipeline_id' => $pipeline->id,
            'name' => 'New',
            'sort_order' => 1,
            'probability' => 10,
            'color' => '#64748b',
        ]);
        $project = Project::query()->create([
            'name' => 'Work',
            'status' => 'active',
            'created_by' => $user->id,
        ]);
        CrmConsultation::query()->create([
            'title' => 'Need a call',
            'status' => 'new',
            'created_by' => $user->id,
            'account_id' => $account->id,
        ]);
        CrmDeal::query()->create([
            'name' => 'Renewal follow-up',
            'account_id' => $account->id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stage->id,
            'assigned_to' => $user->id,
            'close_date' => now()->subDay()->toDateString(),
            'created_by' => $user->id,
        ]);
        CrmActivity::query()->create([
            'type' => 'follow_up',
            'subject' => 'Call the buyer',
            'related_model' => CrmDeal::class,
            'related_id' => 1,
            'due_date' => now()->toDateString(),
            'assigned_to' => $user->id,
            'created_by' => $user->id,
        ]);
        PrjApproval::query()->create([
            'project_id' => $project->id,
            'title' => 'Approve homepage',
            'status' => 'pending',
            'created_by' => $user->id,
        ]);
        WebinoSiteProvision::query()->create([
            'crm_account_id' => $account->id,
            'slug' => 'broken-shop',
            'domain' => 'broken.example',
            'status' => 'failed',
            'error_log' => 'health check failed',
        ]);

        $response = $this->getJson('/api/v1/core/dashboard/crm-pm')->assertOk();
        $kinds = collect($response->json('data.todos.work'))->pluck('kind')->unique()->sort()->values()->all();
        $this->assertContains('consultation', $kinds);
        $this->assertContains('deal', $kinds);
        $this->assertContains('approval', $kinds);
        $response->assertJsonPath('data.todos.sites.0.domain', 'broken.example');
        $response->assertJsonPath('data.ops.sites.sites_failed', 1);
        $this->assertGreaterThanOrEqual(1, $response->json('data.todos.counts.problems'));
    }

    public function test_reports_include_sites_marketing_and_notifications(): void
    {
        $user = $this->actingAsRole(RolesAndPermissionsSeeder::ROLE_SYSTEM_MANAGER);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/core/reports?tab=sites&from=2026-01-01&to=2026-12-31')
            ->assertOk()
            ->assertJsonStructure(['data' => ['stats' => ['sites_total', 'sites_problems'], 'tables' => ['problem_sites']]]);

        $this->getJson('/api/v1/core/reports?tab=marketing&from=2026-01-01&to=2026-12-31')
            ->assertOk()
            ->assertJsonStructure(['data' => ['stats' => ['published_pages', 'form_submissions']]]);

        $this->getJson('/api/v1/core/reports?tab=notifications&from=2026-01-01&to=2026-12-31')
            ->assertOk()
            ->assertJsonStructure(['data' => ['stats' => ['unread_notifications']]]);
    }

    public function test_client_cannot_create_project(): void
    {
        Sanctum::actingAs($this->actingAsRole(RolesAndPermissionsSeeder::ROLE_CLIENT));

        $this->postJson('/api/v1/projects/projects', [
            'name' => 'Denied',
            'status' => 'active',
        ])->assertForbidden();
    }
}
