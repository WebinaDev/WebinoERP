<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\Core\Entities\CoreLicense;
use Modules\Core\Services\SiteAuth;
use Tests\TestCase;

class SiteAuthTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'site-secret-0123456789abcdef0123456789abcdef';

    protected function setUp(): void
    {
        parent::setUp();
        CoreLicense::query()->create([
            'license_key' => 'key-shop-1', 'project_name' => 'Shop', 'domain' => 'shop.test', 'status' => 'active',
        ]);
    }

    /** @return array<string,string> */
    private function signed(string $method, string $path, string $body = '', string $domain = 'shop.test', string $secret = self::SECRET, ?string $nonce = null): array
    {
        $ts = (string) time();
        $nonce ??= Str::random(24);

        return [
            'X-Webino-Site' => $domain,
            'X-Webino-Ts' => $ts,
            'X-Webino-Nonce' => $nonce,
            'X-Webino-Signature' => SiteAuth::sign($secret, $method, $path, $domain, $ts, $nonce, $body),
            'Accept' => 'application/json',
        ];
    }

    private const DASH = '/api/webinocrm/v1/modirpayamak/dashboard';

    public function test_grace_unsigned_unenrolled_domain_still_served(): void
    {
        $this->getJson(self::DASH.'?domain=shop.test')->assertOk()->assertJsonPath('ok', true);
    }

    public function test_enrolled_domain_rejects_unsigned_even_in_grace(): void
    {
        SiteAuth::storeSecret('shop.test', self::SECRET);
        $this->getJson(self::DASH.'?domain=shop.test')->assertStatus(401)->assertJsonPath('code', 'signature_required');
    }

    public function test_valid_signature_passes_and_replay_is_rejected(): void
    {
        SiteAuth::storeSecret('shop.test', self::SECRET);
        $headers = $this->signed('GET', self::DASH);
        $this->signedGet($headers)->assertOk()->assertJsonPath('ok', true);
        $this->signedGet($headers)->assertStatus(401)->assertJsonPath('code', 'invalid_signature');
    }

    public function test_wrong_secret_and_domain_mismatch_are_rejected(): void
    {
        SiteAuth::storeSecret('shop.test', self::SECRET);
        SiteAuth::storeSecret('evil.test', 'evil-secret-0123456789abcdef0123456789');
        $this->signedGet($this->signed('GET', self::DASH, '', 'shop.test', 'wrong'))
            ->assertStatus(401);
        // evil.test signs correctly with its own key but asks for shop.test's wallet.
        $this->signedGet($this->signed('GET', self::DASH, '', 'evil.test', 'evil-secret-0123456789abcdef0123456789'))
            ->assertStatus(401)->assertJsonPath('code', 'domain_mismatch');
    }

    public function test_signature_covers_post_body(): void
    {
        SiteAuth::storeSecret('shop.test', self::SECRET);
        $path = '/api/webinocrm/v1/modirpayamak/send';
        $headers = $this->signed('POST', $path, json_encode(['domain' => 'shop.test', 'message' => 'a']));
        $this->call('POST', $path, [], [], [], $this->server($headers + ['Content-Type' => 'application/json']), json_encode(['domain' => 'shop.test', 'message' => 'TAMPERED']))
            ->assertStatus(401);
    }

    public function test_enforce_mode_rejects_unsigned_unenrolled(): void
    {
        config(['integrations.site_auth.mode' => 'enforce']);
        $this->getJson(self::DASH.'?domain=shop.test')->assertStatus(401);
    }

    public function test_off_mode_skips_checks(): void
    {
        config(['integrations.site_auth.mode' => 'off']);
        SiteAuth::storeSecret('shop.test', self::SECRET);
        $this->getJson(self::DASH.'?domain=shop.test')->assertOk();
    }

    public function test_basalam_disconnect_requires_signature_once_enrolled(): void
    {
        SiteAuth::storeSecret('shop.test', self::SECRET);
        $path = '/api/webinocrm/v1/basalam/oauth/disconnect';
        $this->postJson($path, ['site_url' => 'https://shop.test'])->assertStatus(401);

        $body = json_encode(['site_url' => 'https://shop.test']);
        $this->call('POST', $path, [], [], [], $this->server($this->signed('POST', $path, $body) + ['Content-Type' => 'application/json']), $body)
            ->assertOk()->assertJsonPath('ok', true);
    }

    public function test_enroll_delivers_secret_only_to_the_domain(): void
    {
        Http::fake(['https://shop.test/*' => Http::response(['ok' => true])]);
        $pending = Str::random(40);

        $this->postJson('/api/webinocrm/v1/site-auth/enroll', [
            'domain' => 'shop.test',
            'receive_url' => 'https://evil.test/wp-json/x',
            'pending' => $pending,
        ])->assertStatus(422)->assertJsonPath('code', 'host_mismatch');

        $this->postJson('/api/webinocrm/v1/site-auth/enroll', [
            'domain' => 'shop.test',
            'receive_url' => 'https://shop.test/wp-json/webino-dashboard/v1/site-auth/receive',
            'pending' => $pending,
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertTrue(SiteAuth::isEnrolled('shop.test'));
        Http::assertSent(fn ($r) => $r['pending'] === $pending && strlen((string) $r['secret']) === 64);

        // Second unsigned enroll (e.g. attacker trying to rotate the key) is refused.
        $this->postJson('/api/webinocrm/v1/site-auth/enroll', [
            'domain' => 'shop.test',
            'receive_url' => 'https://shop.test/wp-json/webino-dashboard/v1/site-auth/receive',
            'pending' => Str::random(40),
        ])->assertStatus(409);
    }

    public function test_enroll_not_stored_when_site_rejects_callback(): void
    {
        Http::fake(['https://shop.test/*' => Http::response(['ok' => false], 403)]);
        $this->postJson('/api/webinocrm/v1/site-auth/enroll', [
            'domain' => 'shop.test',
            'receive_url' => 'https://shop.test/wp-json/webino-dashboard/v1/site-auth/receive',
            'pending' => Str::random(40),
        ])->assertStatus(502);
        $this->assertFalse(SiteAuth::isEnrolled('shop.test'));
    }

    /** GET with an empty body, exactly like wp_remote_get(). */
    private function signedGet(array $headers): \Illuminate\Testing\TestResponse
    {
        return $this->call('GET', self::DASH.'?domain=shop.test', [], [], [], $this->server($headers), '');
    }

    /** @param array<string,string> $headers */
    private function server(array $headers): array
    {
        $out = [];
        foreach ($headers as $k => $v) {
            $key = strtoupper(str_replace('-', '_', $k));
            $out[in_array($key, ['CONTENT_TYPE'], true) ? $key : 'HTTP_'.$key] = $v;
        }

        return $out;
    }
}
