<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Entities\CoreInfraAuditLog;
use Modules\Core\Entities\SystemModule;
use Modules\Crm\Entities\CrmAccount;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;
use Modules\SiteBuilder\Services\SiteProvisionOrchestrator;
use Modules\SiteBuilder\Support\ImpersonationPassport;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class SiteImpersonationTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        SystemModule::query()->firstOrCreate(
            ['slug' => 'platform'],
            ['name' => 'Platform', 'is_active' => true]
        );
        config(['app.url' => 'https://erp.test']);
    }

    public function test_staff_receives_signed_dashboard_url_and_audit_without_the_passport(): void
    {
        $staff = $this->actingAsRole('system_manager');
        $staff->forceFill(['name' => 'Sara Staff'])->save();
        Sanctum::actingAs($staff);

        $account = CrmAccount::query()->create(['name' => 'Cafe Customer', 'type' => 'customer']);
        $site = $this->site('cafe', 'cafe.test', $account->id, 'Cafe');
        $other = $this->site('shop', 'shop.test', null, 'Shop');

        $seen = null;
        $this->mock(SiteProvisionOrchestrator::class, function ($mock) use (&$seen) {
            $mock->shouldReceive('callTenantApi')->once()->andReturnUsing(
                function ($provision, $path, $payload) use (&$seen) {
                    $seen = ['path' => $path, 'payload' => $payload, 'domain' => $provision->domain];

                    return ['data' => ['login_url' => 'https://cafe.test/login?panel_token=one', 'expires_in' => 300]];
                }
            );
        });

        $response = $this->postJson('/api/v1/site-builder/provisions/'.$site->id.'/panel-login', [
            'next' => 'https://evil.test/phish',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.url', 'https://cafe.test/login?panel_token=one')
            ->assertJsonPath('data.expires_in', 300);

        $this->assertIsArray($seen);
        $this->assertSame('provision/panel-login', $seen['path']);
        $this->assertSame('/dashboard', $seen['payload']['next']);
        $passport = $seen['payload']['impersonation']['passport'] ?? '';
        $this->assertIsString($passport);
        $this->assertNotSame('', $passport);
        $this->assertStringNotContainsString($passport, $response->getContent());
        $this->assertSame('Sara Staff', $seen['payload']['impersonation']['staff_name']);
        $this->assertSame('Cafe Customer', $seen['payload']['impersonation']['customer_name']);
        $this->assertSame('https://erp.test/dashboard/admin/platform/sites/'.$site->id, $seen['payload']['impersonation']['return_url']);
        $ids = array_column($seen['payload']['impersonation']['sites'], 'provision_id');
        $this->assertEqualsCanonicalizing([$site->id, $other->id], $ids);
        foreach ($seen['payload']['impersonation']['sites'] as $row) {
            $this->assertArrayNotHasKey('passport', $row);
        }

        $log = CoreInfraAuditLog::query()->where('action', 'impersonation.enter')->first();
        $this->assertNotNull($log);
        $this->assertSame($staff->id, $log->user_id);
        $this->assertSame('Cafe Customer', $log->payload['customer_name']);
        $this->assertSame('cafe.test', $log->payload['domain']);
        $this->assertArrayNotHasKey('passport', $log->payload ?? []);

        ImpersonationPassport::parse($passport);
    }

    public function test_exchange_rotates_passport_and_exit_revokes_it(): void
    {
        $staff = $this->actingAsRole('system_manager');
        $site = $this->site('cafe', 'cafe.test', null, 'Cafe');
        $passport = ImpersonationPassport::issue((int) $staff->id);

        $seen = null;
        $this->mock(SiteProvisionOrchestrator::class, function ($mock) use (&$seen) {
            $mock->shouldReceive('callTenantApi')->once()->andReturnUsing(
                function ($provision, $path, $payload) use (&$seen) {
                    $seen = $payload;

                    return ['data' => ['login_url' => 'https://cafe.test/login?panel_token=switched']];
                }
            );
        });

        $this->postJson('/api/v1/site-builder/impersonate/exchange', [
            'passport' => $passport,
            'provision_id' => $site->id,
            'next' => '/dashboard/builder/12',
        ])->assertOk()->assertJsonPath('data.url', 'https://cafe.test/login?panel_token=switched');

        $this->assertSame('/dashboard/builder/12', $seen['next']);
        $fresh = $seen['impersonation']['passport'];
        $this->assertNotSame($passport, $fresh);

        $this->postJson('/api/v1/site-builder/impersonate/exchange', [
            'passport' => $passport,
            'provision_id' => $site->id,
            'next' => '/dashboard',
        ])->assertStatus(401);

        $this->postJson('/api/v1/site-builder/impersonate/exit', [
            'passport' => $fresh,
            'provision_id' => $site->id,
        ])->assertOk()->assertJsonPath(
            'data.return_url',
            'https://erp.test/dashboard/admin/platform/sites/'.$site->id,
        );

        $this->assertNotNull(CoreInfraAuditLog::query()->where('action', 'impersonation.switch')->first());
        $this->assertNotNull(CoreInfraAuditLog::query()->where('action', 'impersonation.exit')->first());

        $this->postJson('/api/v1/site-builder/impersonate/exchange', [
            'passport' => $fresh,
            'provision_id' => $site->id,
        ])->assertStatus(401);
    }

    public function test_client_cannot_impersonate_and_tenant_failure_falls_back_without_a_session(): void
    {
        $client = $this->actingAsRole('client');
        Sanctum::actingAs($client);
        $site = $this->site('cafe', 'cafe.test', null, 'Cafe');

        $this->postJson('/api/v1/site-builder/provisions/'.$site->id.'/panel-login', [
            'next' => '/dashboard/theme-builder',
        ])->assertForbidden();

        $staff = $this->actingAsRole('system_manager');
        Sanctum::actingAs($staff);
        $this->mock(SiteProvisionOrchestrator::class, function ($mock) {
            $mock->shouldReceive('callTenantApi')->once()->andThrow(new \RuntimeException('tenant down'));
        });

        $this->postJson('/api/v1/site-builder/provisions/'.$site->id.'/panel-login', [
            'next' => '/dashboard/theme-builder',
        ])->assertOk()
            ->assertJsonPath('data.fallback', true)
            ->assertJsonPath('data.url', 'https://cafe.test/login?next=%2Fdashboard%2Ftheme-builder');

        $this->assertNull(CoreInfraAuditLog::query()->where('action', 'impersonation.enter')->first());
    }

    public function test_inactive_staff_passport_cannot_switch_sites(): void
    {
        $staff = $this->actingAsRole('system_manager');
        $staff->forceFill(['is_active' => false])->save();
        $site = $this->site('cafe', 'cafe.test', null, 'Cafe');
        $passport = ImpersonationPassport::issue((int) $staff->id);

        $this->mock(SiteProvisionOrchestrator::class, function ($mock) {
            $mock->shouldReceive('callTenantApi')->never();
        });

        $this->postJson('/api/v1/site-builder/impersonate/exchange', [
            'passport' => $passport,
            'provision_id' => $site->id,
            'next' => '/dashboard/builder',
        ])->assertForbidden();
    }

    private function site(string $slug, string $domain, ?int $accountId, string $name): WebinoSiteProvision
    {
        return WebinoSiteProvision::query()->create([
            'crm_account_id' => $accountId,
            'slug' => $slug,
            'domain' => $domain,
            'status' => WebinoSiteProvision::STATUS_READY,
            'wizard_payload' => ['site_name' => $name],
            'provision_token' => 'tok-'.$slug,
        ]);
    }
}
