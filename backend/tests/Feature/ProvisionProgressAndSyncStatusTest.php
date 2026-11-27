<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Entities\CoreHostingSetting;
use Modules\SiteBuilder\Database\Seeders\SiteBuilderSeeder;
use Modules\SiteBuilder\Entities\WebinoPackage;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;
use Modules\Core\Entities\SystemModule;
use Modules\SiteBuilder\Support\ProvisionProgress;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class ProvisionProgressAndSyncStatusTest extends TestCase
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
        $this->seed(SiteBuilderSeeder::class);
        CoreHostingSetting::query()->delete();
        $s = new CoreHostingSetting;
        $s->forceFill([
            'platform_base_domain' => 'webinaagency.ir',
            'provision_webhook_secret' => 'test-secret',
        ])->save();
        Cache::flush();
    }

    private function provision(string $slug, string $status): WebinoSiteProvision
    {
        return WebinoSiteProvision::query()->create([
            'package_id' => WebinoPackage::query()->value('id'),
            'slug' => $slug,
            'domain' => $slug.'.example.test',
            'status' => $status,
            'wizard_payload' => ['site_name' => 'Auto', 'admin_email' => 'a@example.test'],
            'provision_token' => 'tok-'.$slug,
            'progress' => ProvisionProgress::make(ProvisionProgress::PHASE_COMPOSE_UP),
        ]);
    }

    public function test_report_preserves_unsaved_ready_status_and_ready_at(): void
    {
        $p = $this->provision('keep-ready', WebinoSiteProvision::STATUS_PROVISIONING);
        $readyAt = now()->subMinute();
        $p->status = WebinoSiteProvision::STATUS_READY;
        $p->ready_at = $readyAt;

        ProvisionProgress::report($p, ProvisionProgress::PHASE_DONE);

        $p->refresh();
        $this->assertSame(WebinoSiteProvision::STATUS_READY, $p->status);
        $this->assertNotNull($p->ready_at);
        $this->assertEquals($readyAt->format('Y-m-d H:i:s'), $p->ready_at->format('Y-m-d H:i:s'));
        $this->assertSame(ProvisionProgress::PHASE_DONE, $p->progress['phase'] ?? null);
        $this->assertSame(100, (int) ($p->progress['percent'] ?? 0));
    }

    public function test_report_still_aborts_when_row_is_cancelled(): void
    {
        $p = $this->provision('cancelled-row', WebinoSiteProvision::STATUS_CANCELLED);
        $p->status = WebinoSiteProvision::STATUS_READY;
        $p->ready_at = now();

        try {
            ProvisionProgress::report($p, ProvisionProgress::PHASE_DONE);
            $this->fail('Expected cancellation exception');
        } catch (\RuntimeException $e) {
            $this->assertSame('platform.provision_cancelled', $e->getMessage());
        }

        $p->refresh();
        $this->assertSame(WebinoSiteProvision::STATUS_CANCELLED, $p->status);
        $this->assertNull($p->ready_at);
    }

    public function test_advance_provisions_reconciles_stuck_provisioning_when_progress_is_done_and_healthy(): void
    {
        $p = $this->provision('stuck-done', WebinoSiteProvision::STATUS_PROVISIONING);
        $p->update(['progress' => ProvisionProgress::make(ProvisionProgress::PHASE_DONE)]);

        Http::fake([
            'https://stuck-done.example.test/api/v1/health/readiness' => Http::response(['ok' => true]),
            'https://stuck-done.example.test/' => Http::response('ok'),
        ]);

        $this->artisan('site-builder:advance-provisions')->assertSuccessful();

        $p->refresh();
        $this->assertSame(WebinoSiteProvision::STATUS_READY, $p->status);
        $this->assertNotNull($p->ready_at);
        $this->assertSame(ProvisionProgress::PHASE_DONE, $p->progress['phase'] ?? null);
    }

    public function test_sync_status_endpoint_reconciles_and_reports_persian_message(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $p = $this->provision('sync-me', WebinoSiteProvision::STATUS_PROVISIONING);
        $p->update(['progress' => ProvisionProgress::make(ProvisionProgress::PHASE_DONE)]);

        Http::fake([
            'https://sync-me.example.test/api/v1/health/readiness' => Http::response(['ok' => true]),
            'https://sync-me.example.test/' => Http::response('ok'),
        ]);

        $this->postJson('/api/v1/site-builder/provisions/'.$p->id.'/sync-status', [
            'bootstrap' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', WebinoSiteProvision::STATUS_READY)
            ->assertJsonPath('data.id', $p->id);

        $body = $this->postJson('/api/v1/site-builder/provisions/'.$p->id.'/sync-status', [
            'bootstrap' => false,
        ])->assertOk()->json();

        $this->assertIsString($body['message'] ?? null);
        $this->assertNotSame('', trim((string) $body['message']));
        $this->assertSame(WebinoSiteProvision::STATUS_READY, $p->fresh()->status);
    }
}
