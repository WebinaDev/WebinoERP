<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Core\Services\SiteAuth;
use Modules\Crm\Entities\CrmBasalamOrder;
use Modules\Marketplace\Entities\MarketplaceBasalamConnection;
use Modules\Marketplace\Entities\MarketplaceBasalamSetting;
use Modules\Marketplace\Services\BasalamOAuthService;
use Tests\TestCase;

class BasalamIntegrationAuditTest extends TestCase
{
    use RefreshDatabase;

    private function configure(): void
    {
        MarketplaceBasalamSetting::query()->create(['client_id' => '2357', 'client_secret' => 's', 'scopes' => 'vendor.product.read']);
        Http::fake(['*' => Http::response(['access_token' => 'A2', 'refresh_token' => 'R2', 'expires_in' => 3600])]);
    }

    public function test_default_redirect_uri_matches_basalam_registered_one(): void
    {
        $this->assertSame('https://webinaagency.ir/api/basalam/oauth/callback', app(BasalamOAuthService::class)->getConfig()['redirect_uri']);
    }

    public function test_refresh_without_vendor_id_persists_new_tokens(): void
    {
        $this->configure();
        MarketplaceBasalamConnection::query()->create(['site_url' => 'https://shop1.ir', 'vendor_id' => 5, 'status' => 'connected', 'tokens' => ['access_token' => 'A1', 'refresh_token' => 'R1']]);

        $this->postJson('/api/webinocrm/v1/basalam/oauth/refresh', ['refresh_token' => 'R1', 'site_url' => 'https://shop1.ir'])->assertOk();

        $this->assertSame('R2', MarketplaceBasalamConnection::query()->first()->tokens['refresh_token']);
    }

    public function test_refresh_for_enrolled_site_without_signature_does_not_touch_connection(): void
    {
        $this->configure();
        SiteAuth::storeSecret('victim.ir', 'sec');
        MarketplaceBasalamConnection::query()->create(['site_url' => 'https://victim.ir', 'vendor_id' => 5, 'status' => 'connected']);

        $this->postJson('/api/webinocrm/v1/basalam/oauth/refresh', ['refresh_token' => 'X', 'site_url' => 'https://victim.ir', 'vendor_id' => 999])
            ->assertOk()->assertJsonPath('access_token', 'A2');

        $this->assertSame(5, MarketplaceBasalamConnection::query()->first()->vendor_id);
    }

    public function test_ingest_matches_store_by_exact_host_not_substring(): void
    {
        $mine = MarketplaceBasalamConnection::query()->create(['site_url' => 'https://myshop.ir', 'vendor_id' => 1, 'status' => 'connected']);
        $this->postJson('/api/webinocrm/v1/crm/basalam/orders', ['site_url' => 'https://shop.ir', 'orders' => [['id' => 'O1']]])->assertOk();
        $this->assertNull(CrmBasalamOrder::query()->first()->connection_id);

        $this->postJson('/api/webinocrm/v1/crm/basalam/orders', ['site_url' => 'https://www.myshop.ir/', 'orders' => [['id' => 'O2']]])->assertOk();
        $this->assertSame($mine->id, CrmBasalamOrder::query()->where('external_id', 'O2')->first()->connection_id);
    }

    public function test_ingest_rejects_site_url_differing_from_authenticated_domain(): void
    {
        $this->postJson('/api/webinocrm/v1/crm/basalam/orders', ['domain' => 'a.ir', 'site_url' => 'https://b.ir', 'orders' => [['id' => 'O1']]])
            ->assertStatus(422);
        $this->assertSame(0, CrmBasalamOrder::query()->count());
    }

    public function test_ingest_maps_basalam_status_ids_and_toman(): void
    {
        $this->postJson('/api/webinocrm/v1/crm/basalam/orders', ['site_url' => 'https://s.ir', 'orders' => [
            ['id' => 'P1', 'status_id' => 3238, 'total' => 1000, 'currency' => 'IRT'], ['id' => 'P2', 'status' => 3233], ['id' => 'P3', 'status' => 9999],
        ]])->assertOk();
        $o = CrmBasalamOrder::query()->pluck('status', 'external_id');
        $this->assertSame(['P1' => 'shipping', 'P2' => 'canceled', 'P3' => 'basalam_9999'], $o->all());
        $this->assertSame('10000.00', (string) CrmBasalamOrder::query()->where('external_id', 'P1')->value('total'));
    }

    public function test_refresh_returns_absolute_expires_at(): void
    {
        $this->configure();
        $res = $this->postJson('/api/webinocrm/v1/basalam/oauth/refresh', ['refresh_token' => 'R1'])->assertOk();
        $this->assertEqualsWithDelta(time() + 3600, $res->json('expires_at'), 5);
    }

    public function test_handoff_v2_signature_uses_site_secret(): void
    {
        $sig = BasalamOAuthService::handoffSignatureV2('k', 'WWW.Shop.ir', 7, '100', 'n', 'a', 'r', 60);
        $this->assertSame(hash_hmac('sha256', "v2\nbasalam-handoff\nshop.ir\n7\n100\nn\n60\n".hash('sha256', 'a|r'), 'k'), $sig);
    }
}
