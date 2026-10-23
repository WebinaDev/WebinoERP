<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Entities\CoreHostingSetting;
use Modules\Core\Entities\CoreLicense;
use Modules\Core\Entities\SystemModule;
use Modules\SiteBuilder\Database\Seeders\SiteBuilderSeeder;
use Modules\SiteBuilder\Entities\WebinoPackage;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class SiteStatusTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    private const URL = 'https://s1.example.test/api/v1/provision/site-status';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        SystemModule::query()->firstOrCreate(['slug' => 'platform'], ['name' => 'Platform', 'is_active' => true]);
        $this->seed(SiteBuilderSeeder::class);
        CoreHostingSetting::query()->delete();
        (new CoreHostingSetting)->forceFill(['platform_base_domain' => 'webinaagency.ir', 'provision_webhook_secret' => 'test-secret'])->save();
        Cache::flush();
    }

    private function site(array $attrs = []): WebinoSiteProvision
    {
        return WebinoSiteProvision::query()->create(array_merge([
            'package_id' => WebinoPackage::query()->value('id'),
            'slug' => 's1',
            'domain' => 's1.example.test',
            'status' => WebinoSiteProvision::STATUS_READY,
            'wizard_payload' => ['site_name' => 'S1', 'admin_email' => 'a@example.test'],
            'provision_token' => 'tok-s1',
        ], $attrs));
    }

    private function license(?string $expires): CoreLicense
    {
        return CoreLicense::query()->create([
            'license_key' => 'LK-'.uniqid(), 'project_name' => 'S1', 'domain' => uniqid().'.example.test',
            'status' => 'active', 'expires_at' => $expires,
        ]);
    }

    private function echoRemote(string $by = 'platform'): void
    {
        Http::fake([self::URL => function ($req) use ($by) {
            $b = $req->method() === 'PUT' ? json_decode($req->body(), true) : ['status' => 'active'];

            return Http::response(['data' => [
                'status' => $b['status'], 'reason' => $b['reason'] ?? null, 'expires_at' => $b['expires_at'] ?? null,
                'launch_at' => $b['launch_at'] ?? null, 'updated_at' => now()->toIso8601String(), 'updated_by' => $by,
            ]]);
        }]);
    }

    public function test_put_sends_signed_contract_and_mirrors_locally_with_audit(): void
    {
        Sanctum::actingAs($this->actingAsRole('system_manager'));
        $p = $this->site();
        $this->echoRemote();

        $this->putJson("/api/v1/site-builder/provisions/{$p->id}/site-status", [
            'status' => 'coming_soon', 'launch_at' => '2026-11-01T10:00:00+03:30',
        ])->assertOk()->assertJsonPath('data.status', 'coming_soon');

        Http::assertSent(fn ($r) => $r->method() === 'PUT' && $r->url() === self::URL
            && $r->header('X-Provision-Token')[0] === 'tok-s1'
            && $r->header('X-Provision-Signature')[0] === hash_hmac('sha256', $r->body(), 'test-secret')
            && $r['status'] === 'coming_soon' && str_starts_with((string) $r['launch_at'], '2026-11-01'));
        $p->refresh();
        $this->assertSame('coming_soon', $p->site_status);
        $this->assertNotNull($p->site_launch_at);
        $this->assertNotNull($p->site_status_synced_at);
        $this->assertDatabaseHas('core_infra_audit_logs', ['action' => 'site_status.changed', 'subject_id' => (string) $p->id]);
    }

    public function test_suspend_requires_reason_and_invalid_status_rejected(): void
    {
        Sanctum::actingAs($this->actingAsRole('system_manager'));
        $p = $this->site();
        Http::fake();
        $this->putJson("/api/v1/site-builder/provisions/{$p->id}/site-status", ['status' => 'suspended'])->assertStatus(422);
        $this->putJson("/api/v1/site-builder/provisions/{$p->id}/site-status", ['status' => 'banana'])->assertStatus(422);
        Http::assertNothingSent();
    }

    public function test_get_reads_remote_status_set_by_site_admin(): void
    {
        Sanctum::actingAs($this->actingAsRole('system_manager'));
        $p = $this->site(['site_status' => 'coming_soon']);
        Http::fake([self::URL => Http::response(['status' => 'active', 'reason' => null, 'expires_at' => null,
            'launch_at' => null, 'updated_at' => now()->toIso8601String(), 'updated_by' => 'admin'])]);

        $this->getJson("/api/v1/site-builder/provisions/{$p->id}/site-status")
            ->assertOk()->assertJsonPath('data.remote.status', 'active')->assertJsonPath('data.updated_by', 'admin');
        Http::assertSent(fn ($r) => $r->method() === 'GET'
            && $r->header('X-Provision-Signature')[0] === hash_hmac('sha256', '', 'test-secret'));
        $this->assertSame('active', $p->fresh()->site_status);
    }

    public function test_remote_failure_returns_502_and_keeps_local_state(): void
    {
        Sanctum::actingAs($this->actingAsRole('system_manager'));
        $p = $this->site(['site_status' => 'active']);
        Http::fake([self::URL => Http::response(['message' => 'forbidden'], 403)]);
        $this->putJson("/api/v1/site-builder/provisions/{$p->id}/site-status", ['status' => 'suspended', 'reason' => 'x'])->assertStatus(502);
        $p->refresh();
        $this->assertSame('active', $p->site_status);
        $this->assertNotNull($p->site_status_sync_error);
        Http::assertSentCount(1); // 4xx is not retried
    }

    public function test_bootstrap_success_marks_new_site_coming_soon_once(): void
    {
        $p = $this->site(['status' => WebinoSiteProvision::STATUS_SSL_PENDING]);
        Http::fake([
            'https://s1.example.test/api/v1/health/metrics' => Http::response(['ok' => true]),
            'https://s1.example.test/api/v1/provision/bootstrap' => Http::response(['data' => ['ok' => true]]),
            self::URL => Http::response(['status' => 'coming_soon', 'updated_by' => 'platform']),
        ]);
        $this->artisan('site-builder:advance-provisions')->assertSuccessful();
        $p->refresh();
        $this->assertSame('ready', $p->status);
        $this->assertSame('coming_soon', $p->site_status);
        Http::assertSent(fn ($r) => $r->method() === 'PUT' && $r['status'] === 'coming_soon');

        // Re-running bootstrap on an already-live site must not flip it back.
        $p->update(['site_status' => 'active']);
        app(\Modules\SiteBuilder\Services\SiteProvisionOrchestrator::class)->bootstrapSite($p->fresh());
        $this->assertSame('active', $p->fresh()->site_status);
    }

    public function test_license_command_suspends_expired_and_reactivates_only_license_suspensions(): void
    {
        $expired = $this->site(['license_id' => $this->license(now()->subDay()->toDateTimeString())->id, 'site_status' => 'active']);
        $manual = $this->site(['slug' => 's2', 'domain' => 's2.example.test', 'license_id' => $this->license(now()->addYear()->toDateTimeString())->id,
            'site_status' => 'suspended', 'site_status_reason' => 'abuse']);
        $this->echoRemote();
        Http::fake(['https://s2.example.test/*' => Http::response([], 500)]);

        $this->artisan('site-builder:enforce-license-status')->assertSuccessful();
        $expired->refresh();
        $this->assertSame('suspended', $expired->site_status);
        $this->assertSame('license_expired', $expired->site_status_reason);
        $this->assertSame('suspended', $manual->fresh()->site_status);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 's2.example.test'));

        // Renewal -> reactivated
        $expired->license->update(['expires_at' => now()->addYear()]);
        $this->artisan('site-builder:enforce-license-status')->assertSuccessful();
        $this->assertSame('active', $expired->fresh()->site_status);
        $this->assertNull($expired->fresh()->site_status_reason);

        // Already-suspended expired site is not re-pushed
        $expired->license->update(['expires_at' => now()->subHour()]);
        $this->artisan('site-builder:enforce-license-status');
        $count = Http::recorded()->count();
        $this->artisan('site-builder:enforce-license-status');
        $this->assertSame($count, Http::recorded()->count());
    }
}
