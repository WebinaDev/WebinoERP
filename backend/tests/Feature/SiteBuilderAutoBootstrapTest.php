<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Modules\Core\Entities\CoreHostingSetting;
use Modules\SiteBuilder\Database\Seeders\SiteBuilderSeeder;
use Modules\SiteBuilder\Entities\WebinoPackage;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;
use Tests\TestCase;

class SiteBuilderAutoBootstrapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SiteBuilderSeeder::class);
        CoreHostingSetting::query()->delete();
        $s = new CoreHostingSetting;
        $s->forceFill(['platform_base_domain' => 'webinaagency.ir', 'provision_webhook_secret' => 'test-secret'])->save();
        Cache::flush();
    }

    private function pending(string $slug = 'auto-site'): WebinoSiteProvision
    {
        return WebinoSiteProvision::query()->create([
            'package_id' => WebinoPackage::query()->value('id'),
            'slug' => $slug,
            'domain' => $slug.'.example.test',
            'status' => WebinoSiteProvision::STATUS_SSL_PENDING,
            'wizard_payload' => ['site_name' => 'Auto', 'admin_email' => 'a@example.test'],
            'provision_token' => 'tok-'.$slug,
        ]);
    }

    public function test_command_bootstraps_ssl_pending_site_with_signature_and_marks_ready(): void
    {
        $p = $this->pending();
        Http::fake([
            'https://auto-site.example.test/api/v1/health/readiness' => Http::response(['ok' => true]),
            'https://auto-site.example.test/api/v1/provision/bootstrap' => Http::response(['data' => ['ok' => true]]),
        ]);

        $this->artisan('site-builder:advance-provisions')->assertSuccessful();

        $p->refresh();
        $this->assertSame(WebinoSiteProvision::STATUS_READY, $p->status);
        $this->assertNotNull($p->ready_at);
        $this->assertNull($p->error_log);
        Http::assertSent(function ($req) {
            return str_ends_with($req->url(), '/api/v1/provision/bootstrap')
                && $req->header('X-Provision-Token')[0] === 'tok-auto-site'
                && $req->header('X-Provision-Signature')[0] === hash_hmac('sha256', $req->body(), 'test-secret');
        });
    }

    public function test_failure_is_logged_and_backs_off_then_retries(): void
    {
        $p = $this->pending('flaky');
        Http::fakeSequence('https://flaky.example.test/*')
            ->push(['ok' => true])            // health
            ->push(['message' => 'nope'], 500) // bootstrap fails
            ->push(['ok' => true])            // health (retry)
            ->push(['data' => ['ok' => true]]); // bootstrap ok

        $this->artisan('site-builder:advance-provisions')->assertSuccessful();
        $p->refresh();
        $this->assertSame(WebinoSiteProvision::STATUS_SSL_PENDING, $p->status);
        $this->assertStringContainsString('auto-bootstrap attempt 1', (string) $p->error_log);

        // Within the backoff window nothing is attempted.
        $this->artisan('site-builder:advance-provisions')->expectsOutputToContain('backoff')->assertSuccessful();
        Http::assertSentCount(2);

        $this->travel(2)->minutes();
        $this->artisan('site-builder:advance-provisions')->assertSuccessful();
        $this->assertSame(WebinoSiteProvision::STATUS_READY, $p->fresh()->status);
    }

    public function test_unhealthy_site_stays_pending_and_ready_sites_are_ignored(): void
    {
        $p = $this->pending('down');
        $ready = $this->pending('done');
        $ready->update(['status' => WebinoSiteProvision::STATUS_READY]);
        Http::fake(['*' => Http::response('', 503)]);

        $this->artisan('site-builder:advance-provisions')->assertSuccessful();

        $this->assertSame(WebinoSiteProvision::STATUS_SSL_PENDING, $p->fresh()->status);
        $this->assertStringContainsString('health check failed', (string) $p->fresh()->error_log);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'done.example.test'));
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/provision/bootstrap'));
    }
}
