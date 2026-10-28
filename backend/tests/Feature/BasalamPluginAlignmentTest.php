<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Core\Services\SiteAuth;
use Modules\Crm\Entities\CrmBasalamOrder;
use Modules\Marketplace\Entities\MarketplaceBasalamConnection;
use Modules\Marketplace\Entities\MarketplaceBasalamSetting;
use Modules\Marketplace\Services\BasalamOAuthService;
use Tests\TestCase;

/** ERP side aligned with WebinaDashboard PR #6 (d49aa82). */
class BasalamPluginAlignmentTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/webinocrm/v1/crm/basalam/orders';

    public function test_invoice_id_is_the_key_and_aliases_id(): void
    {
        $this->postJson(self::URL, ['site_url' => 'https://s.ir', 'orders' => [['invoice_id' => 'INV1', 'total' => 5]]])->assertOk();
        $this->postJson(self::URL, ['site_url' => 'https://s.ir', 'orders' => [['id' => 'INV1', 'total' => 7]]])->assertOk();
        $this->assertSame(1, CrmBasalamOrder::query()->count());
        $this->assertSame('7.00', (string) CrmBasalamOrder::query()->value('total'));
        $this->postJson(self::URL, ['site_url' => 'https://s.ir', 'orders' => [['total' => 1]]])->assertStatus(422);
    }

    public function test_fee_is_optional_and_converted(): void
    {
        $this->postJson(self::URL, ['site_url' => 'https://s.ir', 'orders' => [
            ['invoice_id' => 'A', 'fee' => 100, 'currency' => 'IRT'], ['invoice_id' => 'B', 'fee' => null], ['invoice_id' => 'C'],
        ]])->assertOk();
        $f = CrmBasalamOrder::query()->pluck('fee', 'external_id');
        $this->assertSame('1000.00', (string) $f['A']);
        $this->assertNull($f['B']);
        $this->assertNull($f['C']);
    }

    public function test_text_statuses_from_woocommerce_mode(): void
    {
        $this->postJson(self::URL, ['site_url' => 'https://s.ir', 'orders' => [
            ['invoice_id' => '1', 'status' => 'bslm-wait-vendor'], ['invoice_id' => '2', 'status' => 'processing'],
            ['invoice_id' => '3', 'status' => 'wc-completed'], ['invoice_id' => '4', 'status' => 'cancelled'],
            ['invoice_id' => '5', 'status' => 'bslm-shipping'], ['invoice_id' => '6', 'status' => 'failed'],
            ['invoice_id' => '7', 'status' => 'my-custom'], ['invoice_id' => '8', 'status_id' => 3195, 'status' => 'pending'],
        ]])->assertOk();
        $this->assertSame([
            '1' => 'wait_vendor', '2' => 'preparation', '3' => 'completed', '4' => 'canceled',
            '5' => 'shipping', '6' => 'rejected', '7' => 'my-custom', '8' => 'completed',
        ], CrmBasalamOrder::query()->orderBy('external_id')->pluck('status', 'external_id')->all());
    }

    public function test_require_signed_flag_blocks_unsigned_ingest(): void
    {
        config(['integrations.basalam.require_signed' => true]);
        $this->postJson(self::URL, ['site_url' => 'https://s.ir', 'orders' => [['invoice_id' => 'X']]])
            ->assertStatus(401);

        SiteAuth::storeSecret('s.ir', str_repeat('k', 64));
        $body = json_encode(['site_url' => 'https://s.ir', 'orders' => [['invoice_id' => 'X']]]);
        $ts = (string) time();
        $nonce = 'nonce_'.str_repeat('a', 20);
        $sig = SiteAuth::sign(str_repeat('k', 64), 'POST', self::URL, 's.ir', $ts, $nonce, $body);
        $this->call('POST', self::URL, [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_WEBINO_SITE' => 's.ir', 'HTTP_X_WEBINO_TS' => $ts, 'HTTP_X_WEBINO_NONCE' => $nonce, 'HTTP_X_WEBINO_SIGNATURE' => $sig,
        ], $body)->assertOk();
    }

    public function test_oauth_start_without_handoff_path_defaults_to_plugin_dashboard(): void
    {
        MarketplaceBasalamSetting::query()->create(['client_id' => '2357', 'client_secret' => 's', 'scopes' => 'x']);
        $res = $this->postJson('/api/webinocrm/v1/basalam/oauth/start', ['site_url' => 'https://shop.ir', 'return_url' => ''])->assertOk();
        $session = Cache::get('marketplace_basalam_oauth_'.$res->json('state'));
        $this->assertSame('/dashboard/settings/shop/basalam/', $session['handoff_path']);
    }

    public function test_registry_exposes_plugin_webhook_paths(): void
    {
        MarketplaceBasalamConnection::query()->create(['site_url' => 'https://shop.ir', 'vendor_id' => 1, 'status' => 'connected']);
        $row = app(BasalamOAuthService::class)->listConnections()[0];
        $this->assertSame('https://shop.ir/wp-json/webino-basalam/v1/order-manager', $row['webhook_url']);
        $this->assertSame('https://shop.ir/wp-json/sync-basalam/v1/order-manager', $row['legacy_webhook_url']);
        $this->assertFalse($row['site_auth_enrolled']);
    }

    public function test_provision_command_lists_missing_and_nudges_without_secrets(): void
    {
        Http::fake(['*' => Http::response(['ok' => true])]);
        MarketplaceBasalamConnection::query()->create(['site_url' => 'https://a.ir', 'vendor_id' => 1, 'status' => 'connected']);
        MarketplaceBasalamConnection::query()->create(['site_url' => 'https://b.ir', 'vendor_id' => 2, 'status' => 'connected']);
        SiteAuth::storeSecret('b.ir', str_repeat('z', 64));

        $this->artisan('site-auth:provision', ['--nudge' => true])
            ->expectsOutputToContain('a.ir')->expectsOutputToContain('missing: 1')->assertSuccessful();
        Http::assertSent(fn ($r) => $r->url() === 'https://a.ir/wp-json/webino-dashboard/v1/site-auth/enroll-now');
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'b.ir'));
        // idempotent, no secret created by the command
        $this->assertFalse(SiteAuth::isEnrolled('a.ir'));
    }

    public function test_provision_nudge_treats_scheduled_as_success_and_429_as_retry_later(): void
    {
        Http::fake([
            'https://s.ir/*' => Http::response(['ok' => true, 'enrolled' => false, 'scheduled' => true]),
            'https://r.ir/*' => Http::response(['ok' => false, 'enrolled' => false, 'code' => 'rate_limited'], 429),
            'https://e.ir/*' => Http::response(['ok' => true, 'enrolled' => true]),
        ]);
        foreach (['s.ir', 'r.ir', 'e.ir'] as $i => $d) {
            MarketplaceBasalamConnection::query()->create(['site_url' => 'https://'.$d, 'vendor_id' => 10 + $i, 'status' => 'connected']);
        }

        $this->artisan('site-auth:provision', ['--nudge' => true])
            ->expectsOutputToContain('nudge=scheduled')
            ->expectsOutputToContain('nudge=retry_later')
            ->expectsOutputToContain('nudge=enrolled')
            ->assertSuccessful();
        $this->assertSame('failed(500)', \Modules\Core\Console\SiteAuthProvisionCommand::classifyNudge(500, []));
    }
}
