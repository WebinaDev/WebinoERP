<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Marketplace\Entities\MarketplaceBasalamSetting;
use Tests\TestCase;

/**
 * The WP Basalam engine (OAuthManager) reads `url` and `access_token` at the top level of
 * the Edge response; the generic API envelope used to hide them under `data`.
 */
class BasalamOAuthEdgeContractTest extends TestCase
{
    use RefreshDatabase;

    private function configure(): void
    {
        MarketplaceBasalamSetting::query()->create([
            'client_id' => '2357',
            'client_secret' => 'test-secret',
            'redirect_uri' => 'https://erp.test/api/basalam/oauth/callback',
            'scopes' => 'vendor.product.read',
        ]);
    }

    public function test_start_returns_top_level_sso_url(): void
    {
        $this->configure();

        $res = $this->postJson('/api/webinocrm/v1/basalam/oauth/start', [
            'site_url' => 'https://shop.example.com',
            'return_url' => 'https://shop.example.com/dashboard/settings/shop/basalam/',
        ])->assertOk();

        $this->assertNotEmpty($res->json('url'));
        $this->assertNull($res->json('success'));
    }

    public function test_refresh_returns_top_level_access_token(): void
    {
        $this->configure();
        Http::fake(['*' => Http::response([
            'access_token' => 'new-access', 'refresh_token' => 'new-refresh', 'expires_in' => 3600,
        ])]);

        $res = $this->postJson('/api/webinocrm/v1/basalam/oauth/refresh', [
            'refresh_token' => 'old-refresh',
        ])->assertOk();

        $this->assertSame('new-access', $res->json('access_token'));
        $this->assertSame('new-refresh', $res->json('refresh_token'));
    }

    public function test_start_without_secret_is_503_with_message(): void
    {
        $res = $this->postJson('/api/webinocrm/v1/basalam/oauth/start', [
            'site_url' => 'https://shop.example.com',
        ])->assertStatus(503);

        $this->assertNotEmpty($res->json('message'));
    }
}
