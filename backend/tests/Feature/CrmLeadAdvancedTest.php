<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Database\Seeders\RolesAndPermissionsSeeder;
use Modules\Core\Entities\SystemModule;
use Modules\Crm\Entities\CrmLead;
use Modules\Crm\Entities\CrmStatus;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class CrmLeadAdvancedTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        SystemModule::create(['name' => 'CRM', 'slug' => 'crm', 'is_active' => true]);
        CrmStatus::create(['name' => 'New', 'color' => '#000', 'sort_order' => 1, 'is_active' => true]);
    }

    public function test_lead_scoring_on_create_and_recompute(): void
    {
        $user = $this->actingAsRole(RolesAndPermissionsSeeder::ROLE_SYSTEM_MANAGER);
        Sanctum::actingAs($user);
        $status = CrmStatus::first();

        $res = $this->postJson('/api/v1/crm/leads', [
            'topic' => 'Enterprise deal',
            'first_name' => 'Ali',
            'last_name' => 'Karimi',
            'email' => 'ali@example.com',
            'mobile' => '09121234567',
            'status_id' => $status->id,
            'rating' => 4,
        ]);
        $res->assertCreated();
        $this->assertGreaterThan(0, (int) $res->json('data.lead_score'));

        $this->postJson('/api/v1/crm/leads/recompute-scores')->assertOk();
    }

    public function test_lead_conversion_creates_account(): void
    {
        $user = $this->actingAsRole(RolesAndPermissionsSeeder::ROLE_SYSTEM_MANAGER);
        Sanctum::actingAs($user);
        $status = CrmStatus::first();
        $lead = CrmLead::create([
            'topic' => 'Convert me',
            'first_name' => 'Sara',
            'last_name' => 'Ahmadi',
            'mobile' => '09120001122',
            'status_id' => $status->id,
            'created_by' => $user->id,
        ]);

        $this->postJson('/api/v1/crm/leads/'.$lead->id.'/convert', [
            'create_contact' => true,
            'create_deal' => false,
        ])->assertOk()->assertJsonPath('data.account.name', 'Sara Ahmadi');

        $this->assertNotNull($lead->fresh()->converted_at);
    }

    public function test_duplicate_detection_and_merge(): void
    {
        $user = $this->actingAsRole(RolesAndPermissionsSeeder::ROLE_SYSTEM_MANAGER);
        Sanctum::actingAs($user);
        $status = CrmStatus::first();

        $a = CrmLead::create([
            'topic' => 'A',
            'first_name' => 'Reza',
            'last_name' => 'Hosseini',
            'email' => 'dup@test.com',
            'mobile' => '09123334455',
            'status_id' => $status->id,
            'created_by' => $user->id,
        ]);
        $b = CrmLead::create([
            'topic' => 'B',
            'first_name' => 'Reza',
            'last_name' => 'Hosseini',
            'email' => 'dup@test.com',
            'mobile' => '09123334455',
            'status_id' => $status->id,
            'created_by' => $user->id,
        ]);

        $this->getJson('/api/v1/crm/leads/'.$a->id.'/duplicates')
            ->assertOk()
            ->assertJsonFragment(['id' => $b->id]);

        $this->postJson('/api/v1/crm/leads/merge', [
            'primary_id' => $a->id,
            'duplicate_id' => $b->id,
        ])->assertOk();

        $this->assertNull(CrmLead::find($b->id));
    }

    public function test_bulk_assign_and_delete(): void
    {
        $user = $this->actingAsRole(RolesAndPermissionsSeeder::ROLE_SYSTEM_MANAGER);
        Sanctum::actingAs($user);
        $status = CrmStatus::first();
        $ids = [];
        foreach (range(1, 2) as $i) {
            $ids[] = CrmLead::create([
                'topic' => "Lead {$i}",
                'first_name' => 'Test',
                'last_name' => (string) $i,
                'mobile' => '0912000000'.$i,
                'status_id' => $status->id,
                'created_by' => $user->id,
            ])->id;
        }

        $this->postJson('/api/v1/crm/leads/bulk-assign', [
            'ids' => $ids,
            'assigned_to' => $user->id,
        ])->assertOk();

        $this->postJson('/api/v1/crm/leads/bulk-delete', ['ids' => $ids])->assertOk();
        $this->assertEquals(0, CrmLead::query()->whereIn('id', $ids)->count());
    }
}
