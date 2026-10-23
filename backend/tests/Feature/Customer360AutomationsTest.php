<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Database\Seeders\RolesAndPermissionsSeeder as R;
use Modules\Core\Entities\WorkflowDefinition;
use Modules\Crm\Entities\CrmAccount;
use Modules\Crm\Entities\CrmMessage;
use Modules\Crm\Events\Customer\ContractSigned;
use Modules\Crm\Services\Automations\AutomationRegistry;
use Modules\Sales\Entities\SalesInvoice;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class Customer360AutomationsTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->seedLicensedModules();
        AutomationRegistry::flush();
    }

    private function toggle(string $key, bool $on): void
    {
        AutomationRegistry::flush();
        AutomationRegistry::all()[$key]->update(['status' => $on ? 'active' : 'inactive']);
        AutomationRegistry::flush();
    }

    public function test_registry_defaults_and_api(): void
    {
        Sanctum::actingAs($this->actingAsRole(R::ROLE_SYSTEM_MANAGER));
        $rows = collect($this->getJson('/api/v1/crm/customers/automations')->assertOk()->json('data'))->keyBy('key');
        $this->assertCount(8, $rows);
        foreach ($rows as $r) {
            $this->assertSame(! $r['sends_sms'] && ! $r['suspends'], $r['enabled'], $r['key']);
        }
        $this->assertSame(8, WorkflowDefinition::query()->where('trigger_key', 'like', 'customer360.%')->count());
        $this->patchJson('/api/v1/crm/customers/automations/auto_suspend', ['enabled' => true, 'config' => ['overdue_days' => 90, 'evil' => 1]])
            ->assertOk()->assertJsonPath('data.enabled', true)->assertJsonPath('data.config.overdue_days', 90)->assertJsonMissingPath('data.config.evil');
        $this->patchJson('/api/v1/crm/customers/automations/nope', ['enabled' => true])->assertNotFound();

        Sanctum::actingAs($this->actingAsRole(R::ROLE_TEAM_MEMBER));
        $this->getJson('/api/v1/crm/customers/automations')->assertForbidden();
    }

    public function test_scheduled_rules_are_idempotent_and_respect_toggles(): void
    {
        $owner = $this->actingAsRole(R::ROLE_SYSTEM_MANAGER);
        $a = CrmAccount::query()->create(['owner_id' => $owner->id, 'name' => 'Late Co', 'type' => 'customer', 'mobile' => '09120000001']);
        SalesInvoice::query()->create(['number' => 'L-1', 'customer_name' => 'x', 'customer_id' => $a->id, 'total' => 900, 'status' => 'unpaid', 'issue_date' => now()->subDays(120)->toDateString()]);
        DB::table('crm_customer_domains')->insert(['account_id' => $a->id, 'domain' => 'late.ir', 'expires_at' => now()->addDays(5)->toDateString(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('crm_customer_documents')->insert(['account_id' => $a->id, 'title' => 'Enamad', 'category' => 'enamad', 'expires_at' => now()->addDays(10)->toDateString(), 'created_at' => now(), 'updated_at' => now()]);

        $this->artisan('crm:automations-run')->assertSuccessful();
        $a->refresh();
        $this->assertSame('debtor', $a->hub_status);
        $this->assertNull($a->suspended_at, 'auto_suspend is off by default');
        $this->assertSame(0, CrmMessage::query()->count(), 'sms rules off by default');
        $this->assertSame(1, DB::table('crm_activities')->where('related_id', $a->id)->where('subject', 'like', 'تمدید مدرک%')->count());

        $this->toggle('auto_suspend', true);
        $this->toggle('renewal_reminder', true);
        $this->artisan('crm:automations-run')->assertSuccessful();
        $this->artisan('crm:automations-run')->assertSuccessful();
        $this->assertNotNull($a->fresh()->suspended_at);
        $this->assertSame(1, CrmMessage::query()->where('crm_account_id', $a->id)->count(), 'renewal sms once (7-day step)');
        $this->assertSame(1, DB::table('crm_activities')->where('related_id', $a->id)->where('subject', 'like', 'تمدید مدرک%')->count());

        SalesInvoice::query()->where('customer_id', $a->id)->update(['status' => 'paid']);
        DB::table('crm_accounts')->where('id', $a->id)->update(['hub_status' => 'debtor', 'suspended_at' => null]);
        $this->artisan('crm:automations-run', ['--only' => ['debtor_status']])->assertSuccessful();
        $this->assertSame('active', $a->fresh()->hub_status);
    }

    public function test_event_rules_onboarding_and_welcome(): void
    {
        $a = CrmAccount::query()->create(['name' => 'New Co', 'type' => 'customer', 'mobile' => '09120000002']);
        $this->assertSame(0, CrmMessage::query()->count());
        ContractSigned::dispatch($a->id, 55, null, null);
        ContractSigned::dispatch($a->id, 55, null, null);
        $this->assertSame(5, DB::table('crm_customer_onboarding_items')->where('account_id', $a->id)->count());

        $this->toggle('welcome_sms', true);
        $b = CrmAccount::query()->create(['name' => 'Hi Co', 'type' => 'customer', 'mobile' => '09120000003']);
        $this->assertSame(1, CrmMessage::query()->where('crm_account_id', $b->id)->count());
    }
}
