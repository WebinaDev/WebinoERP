<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Database\Seeders\RolesAndPermissionsSeeder;
use Modules\Core\Entities\SystemModule;
use Modules\Projects\Entities\Project;
use Modules\Projects\Entities\ProjectTask;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class ProjectsApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        SystemModule::create(['name' => 'Projects', 'slug' => 'projects', 'is_active' => true]);
    }

    public function test_team_member_can_list_projects(): void
    {
        $user = $this->actingAsRole(RolesAndPermissionsSeeder::ROLE_TEAM_MEMBER);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/projects/projects')->assertOk();
    }

    public function test_system_manager_can_create_project(): void
    {
        $user = $this->actingAsRole(RolesAndPermissionsSeeder::ROLE_SYSTEM_MANAGER);
        Sanctum::actingAs($user);

        $create = $this->postJson('/api/v1/projects/projects', [
            'name' => 'RBAC Project',
            'status' => 'active',
        ]);
        $create->assertCreated();
    }

    public function test_client_cannot_create_project(): void
    {
        $user = $this->actingAsRole(RolesAndPermissionsSeeder::ROLE_CLIENT);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/projects/projects', [
            'name' => 'Denied',
            'status' => 'active',
        ])->assertForbidden();
    }

    public function test_system_manager_can_update_project(): void
    {
        $user = $this->actingAsRole(RolesAndPermissionsSeeder::ROLE_SYSTEM_MANAGER);
        Sanctum::actingAs($user);

        $project = Project::query()->create([
            'name' => 'Original',
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $this->patchJson('/api/v1/projects/projects/'.$project->id, [
            'name' => 'Updated Name',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Name');
    }

    public function test_system_manager_can_delete_project(): void
    {
        $user = $this->actingAsRole(RolesAndPermissionsSeeder::ROLE_SYSTEM_MANAGER);
        Sanctum::actingAs($user);

        $project = Project::query()->create([
            'name' => 'To Delete',
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $this->deleteJson('/api/v1/projects/projects/'.$project->id)
            ->assertNoContent();

        $this->assertDatabaseMissing('prj_projects', ['id' => $project->id]);
    }

    public function test_system_manager_can_create_contract(): void
    {
        $user = $this->actingAsRole(RolesAndPermissionsSeeder::ROLE_SYSTEM_MANAGER);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/projects/contracts', [
            'title' => 'Service Agreement',
            'amount' => 1500,
        ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Service Agreement')
            ->assertJsonPath('data.status', 'draft');
    }

    public function test_system_manager_can_create_task(): void
    {
        $user = $this->actingAsRole(RolesAndPermissionsSeeder::ROLE_SYSTEM_MANAGER);
        Sanctum::actingAs($user);

        $project = Project::query()->create([
            'name' => 'Task Project',
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $this->postJson('/api/v1/projects/tasks', [
            'project_id' => $project->id,
            'title' => 'Implement feature',
        ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Implement feature')
            ->assertJsonPath('data.status', 'open');
    }

    public function test_system_manager_can_store_ticket(): void
    {
        $user = $this->actingAsRole(RolesAndPermissionsSeeder::ROLE_SYSTEM_MANAGER);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/projects/tickets', [
            'subject' => 'Support request',
            'body' => 'Need help with billing',
            'priority' => 'high',
        ])
            ->assertCreated()
            ->assertJsonPath('data.subject', 'Support request')
            ->assertJsonPath('data.status', 'open');
    }

    public function test_system_manager_can_run_sprint_lifecycle(): void
    {
        $user = $this->actingAsRole(RolesAndPermissionsSeeder::ROLE_SYSTEM_MANAGER);
        Sanctum::actingAs($user);

        $project = Project::query()->create([
            'name' => 'Sprint Project',
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $sprintResponse = $this->postJson('/api/v1/projects/sprints', [
            'project_id' => $project->id,
            'name' => 'Sprint 1',
        ]);
        $sprintResponse->assertCreated();
        $sprintId = $sprintResponse->json('data.id');

        $task = ProjectTask::query()->create([
            'project_id' => $project->id,
            'title' => 'Sprint task',
            'status' => 'open',
            'created_by' => $user->id,
        ]);

        $this->postJson('/api/v1/projects/sprints/'.$sprintId.'/tasks', [
            'task_id' => $task->id,
        ])->assertCreated();

        $this->postJson('/api/v1/projects/sprints/'.$sprintId.'/start')
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->postJson('/api/v1/projects/sprints/'.$sprintId.'/finish')
            ->assertOk()
            ->assertJsonPath('data.sprint.status', 'completed')
            ->assertJsonStructure(['data' => ['burndown' => ['total_tasks', 'completed_tasks', 'remaining_tasks']]]);
    }
}
