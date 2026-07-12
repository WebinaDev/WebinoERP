<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Database\Seeders\RolesAndPermissionsSeeder;
use Modules\Core\Entities\CoreAutomationRule;
use Modules\Crm\Entities\CrmLead;
use Modules\Crm\Entities\CrmStatus;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class CrmAutomationTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_lead_status_change_dispatches_automation_trigger(): void
    {
        $user = $this->actingAsRole(RolesAndPermissionsSeeder::ROLE_SYSTEM_MANAGER);
        Sanctum::actingAs($user);

        $from = CrmStatus::query()->create(['name' => 'New', 'color' => '#000']);
        $to = CrmStatus::query()->create(['name' => 'Qualified', 'color' => '#0f0']);
        $lead = CrmLead::query()->create([
            'topic' => 'Auto lead',
            'first_name' => 'A',
            'last_name' => 'B',
            'mobile' => '09120001111',
            'status_id' => $from->id,
            'created_by' => $user->id,
        ]);

        CoreAutomationRule::query()->create([
            'name' => 'Notify on qualify',
            'trigger' => 'crm.lead.status_changed',
            'conditions' => ['status_id' => $to->id],
            'actions' => [['type' => 'notify', 'payload' => ['user_id' => $user->id, 'title' => 'Lead qualified']]],
            'is_active' => true,
            'priority' => 10,
            'created_by' => $user->id,
        ]);

        $this->patchJson("/api/v1/crm/leads/{$lead->id}/status", ['status_id' => $to->id])
            ->assertOk()
            ->assertJsonPath('data.status_id', $to->id);

        $this->assertDatabaseHas('core_notifications', [
            'user_id' => $user->id,
            'type' => 'automation',
        ]);
    }
}
