<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Database\Seeders\RolesAndPermissionsSeeder as R;
use Modules\Core\Entities\CoreHostingSetting;
use Modules\Core\Entities\SystemModule;
use Modules\Crm\Entities\CrmAccount;
use Modules\Crm\Services\Hub\SiteCustomerSummary;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class SiteCustomerHubTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    private CrmAccount $acc;

    private WebinoSiteProvision $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->seedLicensedModules();
        SystemModule::query()->firstOrCreate(['slug' => 'platform'], ['name' => 'Platform', 'is_active' => true]);
        CoreHostingSetting::query()->delete();
        (new CoreHostingSetting)->forceFill(['platform_base_domain' => 'example.test', 'provision_webhook_secret' => 's'])->save();
        Cache::flush();
        $this->acc = CrmAccount::query()->create(['name' => 'Ali Co', 'type' => 'customer', 'account_code' => 'C-1', 'kyc_status' => 'verified']);
        DB::table('crm_contacts')->insert(['account_id' => $this->acc->id, 'first_name' => 'Ali', 'last_name' => 'K', 'email' => 'ali@ali.ir',
            'mobile' => '09120000000', 'is_primary' => true, 'created_at' => now(), 'updated_at' => now()]);
        $lic = DB::table('core_licenses')->insertGetId(['license_key' => 'KEY-123456', 'domain' => 'ali.ir', 'product' => 'webino', 'status' => 'active',
            'expires_at' => now()->addDays(10), 'crm_account_id' => $this->acc->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->site = WebinoSiteProvision::query()->create(['crm_account_id' => $this->acc->id, 'license_id' => $lic, 'slug' => 'ali', 'domain' => 'ali.ir',
            'status' => 'ready', 'provision_token' => 't', 'site_status' => 'coming_soon']);
        DB::table('prj_tickets')->insert(['customer_account_id' => $this->acc->id, 'subject' => 'Font broken', 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_admin_gets_full_owner_summary(): void
    {
        Sanctum::actingAs($this->actingAsRole(R::ROLE_SYSTEM_MANAGER));
        $res = $this->getJson("/api/v1/site-builder/provisions/{$this->site->id}/customer-hub")->assertOk();
        $res->assertJsonPath('data.linked', true)
            ->assertJsonPath('data.customer.name', 'Ali Co')
            ->assertJsonPath('data.identity.kyc.status', 'verified')
            ->assertJsonPath('data.identity.contacts.0.email', 'ali@ali.ir')
            ->assertJsonPath('data.licenses.0.this_site', true)
            ->assertJsonPath('data.licenses.0.effective_status', 'expiring')
            ->assertJsonPath('data.site_status.status', 'coming_soon')
            ->assertJsonPath('data.support.open', 1)
            ->assertJsonPath('data.support.recent.0.subject', 'Font broken')
            ->assertJsonPath('data.assets.sites.0.this_site', true)
            ->assertJsonPath('data.can_link', true);
        $this->assertArrayHasKey('billing', $res->json('data'));
        $this->assertArrayHasKey('usage', $res->json('data'));
        $this->assertStringNotContainsString('KEY-123456', $res->getContent());
        $codes = collect($res->json('data.alerts'))->pluck('code');
        $this->assertTrue($codes->contains('license_expiring'));
    }

    public function test_unlinked_site_offers_link_and_link_endpoint_sets_owner(): void
    {
        Sanctum::actingAs($this->actingAsRole(R::ROLE_SYSTEM_MANAGER));
        $this->site->update(['crm_account_id' => null]);
        $this->getJson("/api/v1/site-builder/provisions/{$this->site->id}/customer-hub")->assertOk()
            ->assertJsonPath('data.linked', false)->assertJsonPath('data.can_link', true);
        $this->putJson("/api/v1/site-builder/provisions/{$this->site->id}/customer-hub/link", ['crm_account_id' => $this->acc->id])->assertOk()
            ->assertJsonPath('data.linked', true)->assertJsonPath('data.customer.id', $this->acc->id);
        $this->assertSame($this->acc->id, (int) $this->site->fresh()->crm_account_id);
        $this->putJson("/api/v1/site-builder/provisions/{$this->site->id}/customer-hub/link", ['crm_account_id' => 999999])->assertStatus(422);
    }

    public function test_sections_follow_customer_hub_access(): void
    {
        $u = User::factory()->create(['is_active' => true]);
        $u->givePermissionTo('crm.accounts.view');
        $d = app(SiteCustomerSummary::class)->build($this->site, $u);
        $this->assertTrue($d['linked']);
        $this->assertArrayHasKey('identity', $d);
        $this->assertArrayNotHasKey('billing', $d);
        $this->assertArrayNotHasKey('support', $d);
        $this->assertNull($d['kpis']['outstanding']);
        $this->assertFalse($d['can_link']);
        $this->assertFalse(collect($d['timeline'] ?? [])->contains(fn ($e) => in_array($e['kind'], ['ticket', 'invoice', 'payment'], true)));

        $none = User::factory()->create(['is_active' => true]);
        $r = app(SiteCustomerSummary::class)->build($this->site, $none);
        $this->assertTrue($r['restricted']);
        $this->assertArrayNotHasKey('identity', $r);
    }

    public function test_link_requires_manage_permission(): void
    {
        $u = User::factory()->create(['is_active' => true]);
        $u->givePermissionTo('crm.accounts.view');
        $this->assertFalse(app(\Modules\Crm\Services\Hub\CustomerHubAccess::class)->canManage($u, 'profile'));
    }
}
