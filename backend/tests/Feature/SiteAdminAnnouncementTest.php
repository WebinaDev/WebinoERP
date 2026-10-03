<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Entities\CoreHostingSetting;
use Modules\Core\Entities\SystemModule;
use Modules\Platform\Entities\PlatformTag;
use Modules\SiteBuilder\Database\Seeders\SiteBuilderSeeder;
use Modules\SiteBuilder\Entities\WebinoBusinessCategory;
use Modules\SiteBuilder\Entities\WebinoBusinessType;
use Modules\SiteBuilder\Entities\WebinoSiteAnnouncement;
use Modules\SiteBuilder\Entities\WebinoSiteAnnouncementDelivery;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class SiteAdminAnnouncementTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    private string $secret = 'announcement-hmac-secret';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        SystemModule::query()->firstOrCreate(
            ['slug' => 'platform'],
            ['name' => 'Platform', 'is_active' => true]
        );
        $this->seed(SiteBuilderSeeder::class);
        CoreHostingSetting::query()->create([
            'platform_base_domain' => 'example.test',
            'provision_webhook_secret' => $this->secret,
        ]);
    }

    public function test_staff_can_publish_to_selected_ready_sites_and_queue_the_rest(): void
    {
        Http::fake([
            'https://cafe.example.test/*' => Http::response(['data' => ['ok' => true]], 200),
        ]);

        $ready = $this->site('cafe', 'cafe.example.test', WebinoSiteProvision::STATUS_READY, [
            'site_type_slug' => 'cafe',
        ]);
        $draft = $this->site('shop', 'shop.example.test', WebinoSiteProvision::STATUS_DRAFT, [
            'site_type_slug' => 'ecommerce',
        ]);

        $this->act();
        $created = $this->postJson('/api/v1/site-builder/announcements', [
            'title_fa' => 'قطع برق',
            'title_en' => 'Power cut',
            'body_fa' => 'امشب سرورها یک ساعت در دسترس نیستند.',
            'body_en' => 'Servers pause for one hour tonight.',
            'level' => 'warning',
            'audience_mode' => 'sites',
            'site_ids' => [$ready->id, $draft->id],
            'publish' => true,
        ]);

        $created->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.delivery.delivered', 1)
            ->assertJsonPath('data.delivery.pending', 1);

        $this->assertDatabaseHas('webino_site_announcement_deliveries', [
            'site_provision_id' => $ready->id,
            'status' => WebinoSiteAnnouncementDelivery::STATUS_DELIVERED,
        ]);
        $this->assertDatabaseHas('webino_site_announcement_deliveries', [
            'site_provision_id' => $draft->id,
            'status' => WebinoSiteAnnouncementDelivery::STATUS_PENDING,
        ]);

        Http::assertSent(function ($request) use ($ready) {
            $body = (string) $request->body();
            $signature = $request->header('X-Provision-Signature')[0] ?? '';

            return $request->url() === 'https://cafe.example.test/api/v1/provision/announcements'
                && ($request->header('X-Provision-Token')[0] ?? '') === $ready->provision_token
                && hash_equals(hash_hmac('sha256', $body, $this->secret), $signature)
                && str_contains($body, 'قطع برق')
                && str_contains($body, '"action":"upsert"');
        });
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'shop.example.test'));
    }

    public function test_audience_filters_cover_all_category_type_and_tag(): void
    {
        $category = WebinoBusinessCategory::query()->where('slug', 'site_types')->firstOrFail();
        $cafeType = WebinoBusinessType::query()->where('slug', 'cafe')->firstOrFail();
        $shopType = WebinoBusinessType::query()->where('slug', 'ecommerce')->firstOrFail();

        $cafe = $this->site('cafe', 'cafe.example.test', WebinoSiteProvision::STATUS_READY, [
            'site_type_slug' => 'cafe',
            'business_type_id' => $cafeType->id,
            'business_category_id' => $category->id,
            'tags' => ['VIP'],
        ]);
        $shop = $this->site('shop', 'shop.example.test', WebinoSiteProvision::STATUS_READY, [
            'site_type_slug' => 'ecommerce',
            'business_type_id' => $shopType->id,
            'business_category_id' => $category->id,
        ]);
        $this->site('draft', 'draft.example.test', WebinoSiteProvision::STATUS_DRAFT, [
            'site_type_slug' => 'cafe',
            'business_category_id' => $category->id,
            'tags' => ['vip'],
        ]);

        $tag = PlatformTag::query()->create(['name' => 'beta', 'color' => '#336699']);
        DB::table('platform_taggables')->insert([
            'tag_id' => $tag->id,
            'taggable_type' => WebinoSiteProvision::class,
            'taggable_id' => $shop->id,
        ]);

        $this->act();

        $this->postJson('/api/v1/site-builder/announcements/preview', [
            'audience_mode' => 'all',
        ])->assertOk()->assertJsonPath('data.count', 2);

        $this->postJson('/api/v1/site-builder/announcements/preview', [
            'audience_mode' => 'category',
            'category_ids' => [$category->id],
        ])->assertOk()->assertJsonPath('data.count', 2);

        $typePreview = $this->postJson('/api/v1/site-builder/announcements/preview', [
            'audience_mode' => 'type',
            'type_ids' => [$cafeType->id],
        ]);
        $typePreview->assertOk()->assertJsonPath('data.count', 1);
        $this->assertSame($cafe->id, $typePreview->json('data.sites.0.id'));

        $tagPreview = $this->postJson('/api/v1/site-builder/announcements/preview', [
            'audience_mode' => 'tag',
            'tags' => ['vip', 'beta'],
        ]);
        $tagPreview->assertOk()->assertJsonPath('data.count', 2);
        $ids = collect($tagPreview->json('data.sites'))->pluck('id')->sort()->values()->all();
        $this->assertSame([$cafe->id, $shop->id], $ids);
    }

    public function test_tenant_pulls_notices_and_reports_read_and_dismiss(): void
    {
        Http::fake();
        $site = $this->site('cafe', 'cafe.example.test', WebinoSiteProvision::STATUS_READY, [
            'site_type_slug' => 'cafe',
        ]);
        $this->act();
        $created = $this->postJson('/api/v1/site-builder/announcements', [
            'title_fa' => 'به‌روزرسانی',
            'body_fa' => 'نسخه جدید آماده است.',
            'audience_mode' => 'all',
            'publish' => true,
        ])->assertOk();
        $key = 'erp-site-announcement:'.$created->json('data.id');

        $late = $this->site('late', 'late.example.test', WebinoSiteProvision::STATUS_READY, [
            'site_type_slug' => 'corporate',
        ]);

        $pull = $this->signed('POST', '/api/v1/site-builder/sync/announcements/pull', [], (string) $late->provision_token);
        $pull->assertOk();
        $this->assertSame($key, $pull->json('data.announcements.0.announcement.external_key'));
        $this->assertSame('upsert', $pull->json('data.announcements.0.announcement.action'));
        $this->assertSame('به‌روزرسانی', $pull->json('data.announcements.0.announcement.title_fa'));

        $read = $this->signed('POST', '/api/v1/site-builder/sync/announcements/receipt', [
            'external_key' => $key,
            'event' => 'read',
        ], (string) $late->provision_token);
        $read->assertOk();
        $this->assertNotNull($read->json('data.read_at'));

        $dismissed = $this->signed('POST', '/api/v1/site-builder/sync/announcements/receipt', [
            'external_key' => $key,
            'event' => 'dismissed',
        ], (string) $site->provision_token);
        $dismissed->assertOk();

        $row = WebinoSiteAnnouncement::query()->firstOrFail();
        $this->getJson('/api/v1/site-builder/announcements/'.$row->id)
            ->assertOk()
            ->assertJsonPath('data.delivery.read', 2)
            ->assertJsonPath('data.delivery.dismissed', 1);

        $bad = $this->signed('POST', '/api/v1/site-builder/sync/announcements/pull', ['tamper' => true], (string) $late->provision_token);
        $bad->assertForbidden();
    }

    public function test_archive_revokes_a_delivered_notice(): void
    {
        Http::fake([
            'https://cafe.example.test/*' => Http::response(['data' => ['ok' => true]], 200),
        ]);
        $this->site('cafe', 'cafe.example.test', WebinoSiteProvision::STATUS_READY, [
            'site_type_slug' => 'cafe',
        ]);
        $this->act();
        $id = $this->postJson('/api/v1/site-builder/announcements', [
            'title_fa' => 'موقت',
            'body_fa' => 'این پیام موقت است.',
            'audience_mode' => 'all',
            'publish' => true,
        ])->assertOk()->json('data.id');

        $this->postJson('/api/v1/site-builder/announcements/'.$id.'/archive')
            ->assertOk()
            ->assertJsonPath('data.status', 'archived')
            ->assertJsonPath('data.delivery.revoked', 1);

        Http::assertSent(fn ($request) => str_contains((string) $request->body(), '"action":"revoke"'));
    }

    public function test_clients_cannot_compose_notices(): void
    {
        $user = $this->actingAsRole('client');
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/site-builder/announcements', [
            'title_fa' => 'نه',
            'body_fa' => 'نه',
            'audience_mode' => 'all',
        ])->assertForbidden();
    }

    private function act(): void
    {
        Sanctum::actingAs($this->actingAsRole('system_manager'));
    }

    /**
     * @param  array<string, mixed>  $wizard
     */
    private function site(string $slug, string $domain, string $status, array $wizard): WebinoSiteProvision
    {
        return WebinoSiteProvision::query()->create([
            'slug' => $slug,
            'domain' => $domain,
            'status' => $status,
            'provision_token' => 'token-'.$slug,
            'wizard_payload' => $wizard,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function signed(string $method, string $uri, array $payload, string $token)
    {
        $body = json_encode($payload === [] ? new \stdClass : $payload, JSON_UNESCAPED_UNICODE);
        $server = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_PROVISION_TOKEN' => $token,
            'HTTP_X_PROVISION_SIGNATURE' => hash_hmac('sha256', (string) $body, $this->secret),
        ];
        if ($uri === '/api/v1/site-builder/sync/announcements/pull' && isset($payload['tamper'])) {
            $server['HTTP_X_PROVISION_SIGNATURE'] = 'not-the-signature';
            unset($payload['tamper']);
            $body = '{}';
        }

        return $this->call($method, $uri, [], [], [], $server, (string) $body);
    }
}
