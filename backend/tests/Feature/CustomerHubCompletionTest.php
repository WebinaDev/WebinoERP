<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Database\Seeders\RolesAndPermissionsSeeder as R;
use Modules\Core\Entities\CoreHostingSetting;
use Modules\Crm\Entities\CrmAccount;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class CustomerHubCompletionTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    private CrmAccount $acc;

    private string $secret = 'hub-hmac-secret';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->seedLicensedModules();
        CoreHostingSetting::query()->create(['platform_base_domain' => 'example.test', 'provision_webhook_secret' => $this->secret]);
        $this->acc = CrmAccount::query()->create(['name' => 'Hub Co', 'type' => 'customer', 'account_code' => 'C-9']);
        DB::table('crm_contacts')->insert(['account_id' => $this->acc->id, 'first_name' => 'Ali', 'last_name' => 'R', 'email' => 'ali@hubco.ir',
            'mobile' => '09120000000', 'is_primary' => true, 'created_at' => now(), 'updated_at' => now()]);
        WebinoSiteProvision::query()->create(['crm_account_id' => $this->acc->id, 'slug' => 'hubco', 'domain' => 'hubco.ir', 'status' => 'ready', 'provision_token' => 'tok-1']);
        DB::table('core_licenses')->insert(['license_key' => 'K-9', 'domain' => 'hubco.ir', 'product' => 'webino', 'status' => 'active',
            'expires_at' => now()->addYear(), 'crm_account_id' => $this->acc->id, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function admin(): void
    {
        Sanctum::actingAs($this->actingAsRole(R::ROLE_SYSTEM_MANAGER));
    }

    public function test_site_panel_calls_signed_erp_link_and_returns_orders_tickets_kyc(): void
    {
        Http::fake(['https://hubco.ir/api/v1/erp-link/customer' => Http::response(['data' => [
            'is_owner_site' => true, 'panel_url' => 'https://hubco.ir/dashboard',
            'matches' => [['user' => ['id' => 5, 'email' => 'ali@hubco.ir'], 'kyc' => ['status' => 'verified'], 'orders' => [['id' => 1]], 'tickets' => []]],
        ]])]);
        $this->admin();
        $this->getJson("/api/v1/crm/customers/{$this->acc->id}/hub/site_panel")->assertOk()
            ->assertJsonPath('data.sites.0.ok', true)
            ->assertJsonPath('data.sites.0.matches.0.kyc.status', 'verified')
            ->assertJsonPath('data.lookup.email', 'ali@hubco.ir');
        Http::assertSent(function (HttpRequest $req) {
            $body = $req->body();

            return $req->hasHeader('X-Provision-Token', 'tok-1')
                && $req->header('X-Provision-Signature')[0] === hash_hmac('sha256', $body, $this->secret)
                && isset(json_decode($body, true)['ts']) && json_decode($body, true)['erp_customer_id'] === $this->acc->id;
        });
    }

    public function test_site_panel_reports_unreachable_site_without_failing(): void
    {
        Http::fake(['*' => Http::response('down', 502)]);
        $this->admin();
        $this->getJson("/api/v1/crm/customers/{$this->acc->id}/hub/site_panel")->assertOk()
            ->assertJsonPath('data.sites.0.ok', false)->assertJsonPath('data.sites.0.domain', 'hubco.ir');
    }

    public function test_usage_collector_upserts_metrics_and_backs_off_on_failure(): void
    {
        Http::fakeSequence('hubco.ir/*')->push('err', 500)->push(['data' => ['metrics' => ['orders' => 12, 'customers' => 4, 'storage_mb' => 30.5]]])
            ->push(['data' => ['metrics' => ['orders' => 13]]]);
        $this->artisan('crm:hub-sync-sites')->assertSuccessful();
        $link = DB::table('crm_customer_site_links')->first();
        $this->assertSame(1, (int) $link->usage_attempts);
        $this->assertNotNull($link->usage_next_at);
        $this->artisan('crm:hub-sync-sites')->expectsOutputToContain('skipped(backoff/fresh)=1')->assertSuccessful();
        $this->artisan('crm:hub-sync-sites --force')->assertSuccessful();
        $this->assertSame(0, (int) DB::table('crm_customer_site_links')->value('usage_attempts'));
        $this->assertEquals(12, DB::table('crm_customer_usage_metrics')->where('metric', 'orders')->value('value'));
        // re-run same day replaces instead of duplicating
        $this->artisan('crm:hub-sync-sites --force')->assertSuccessful();
        $this->assertSame(1, DB::table('crm_customer_usage_metrics')->where('metric', 'orders')->count());
        $this->admin();
        $this->getJson("/api/v1/crm/customers/{$this->acc->id}/hub/usage")->assertOk()->assertJsonPath('data.site_orders', 13);
    }

    public function test_document_upload_private_storage_and_signed_download_respects_visibility(): void
    {
        Storage::fake('local');
        $this->admin();
        $res = $this->post("/api/v1/crm/customers/{$this->acc->id}/hub/documents", [
            'category' => 'contract', 'title' => 'قرارداد', 'file' => UploadedFile::fake()->create('c.pdf', 200, 'application/pdf'),
            'visible_to_roles' => json_encode(['finance_only_role']),
        ], ['Accept' => 'application/json'])->assertCreated();
        $docId = $res->json('data.id');
        $this->assertTrue($res->json('data.has_file'));
        $this->assertArrayNotHasKey('file_path', $res->json('data'));
        $path = DB::table('crm_customer_documents')->where('id', $docId)->value('file_path');
        Storage::disk('local')->assertExists($path);

        $link = $this->postJson("/api/v1/crm/customers/{$this->acc->id}/hub/documents/{$docId}/download-link")->assertOk()->json('data.url');
        $this->get($link)->assertOk()->assertHeader('Cache-Control');
        $this->get(preg_replace('/signature=[^&]+/', 'signature=bad', $link))->assertForbidden();

        // too large / bad type
        $this->post("/api/v1/crm/customers/{$this->acc->id}/hub/documents", ['category' => 'other', 'title' => 'x',
            'file' => UploadedFile::fake()->create('a.exe', 20, 'application/x-msdownload')], ['Accept' => 'application/json'])->assertStatus(422);
        $this->post("/api/v1/crm/customers/{$this->acc->id}/hub/documents", ['category' => 'other', 'title' => 'x',
            // limit is 20MB since Customer 360 phase 1 (PLAN §5.1)
            'file' => UploadedFile::fake()->create('big.pdf', 20481, 'application/pdf')], ['Accept' => 'application/json'])->assertStatus(422);

        // a role outside visible_to_roles cannot obtain a link
        Sanctum::actingAs($this->actingAsRole(R::ROLE_CRM_SPECIALIST));
        if (! in_array('finance_only_role', auth()->user()->getRoleNames()->all(), true) && ! app(\Modules\Crm\Services\Hub\CustomerHubAccess::class)->isAdmin(auth()->user())
            && app(\Modules\Crm\Services\Hub\CustomerHubAccess::class)->canView(auth()->user(), 'documents')) {
            $this->postJson("/api/v1/crm/customers/{$this->acc->id}/hub/documents/{$docId}/download-link")->assertNotFound();
        }

        $this->admin();
        $this->deleteJson("/api/v1/crm/customers/{$this->acc->id}/hub/documents/{$docId}")->assertNoContent(); // 204 per PLAN §5.1
        Storage::disk('local')->assertMissing($path);
    }

    public function test_suspend_and_activate_are_reversible_and_push_to_sites(): void
    {
        Http::fake(['*' => Http::response(['data' => ['ok' => true]])]);
        DB::table('crm_customer_services')->insert(['account_id' => $this->acc->id, 'kind' => 'hosting', 'name' => 'Hosting', 'status' => 'trial', 'created_at' => now(), 'updated_at' => now()]);
        $this->admin();
        $id = $this->acc->id;
        $this->postJson("/api/v1/crm/customers/{$id}/hub/actions/suspend", ['reason' => 'debt'])->assertStatus(422); // confirm required
        $this->postJson("/api/v1/crm/customers/{$id}/hub/actions/suspend", ['reason' => 'debt', 'confirm' => true])->assertOk()
            ->assertJsonPath('data.action_result.licenses', 1)->assertJsonPath('data.action_result.services', 1)->assertJsonPath('data.action_result.sites.0.ok', true);
        $this->assertSame('suspended', DB::table('core_licenses')->value('status'));
        $this->assertSame('suspended', DB::table('crm_customer_services')->value('status'));
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/erp-link/suspension') && $r['suspended'] === true);
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/provision/license-sync'));
        $this->assertDatabaseHas('crm_customer_audit', ['account_id' => $id, 'action' => 'account.suspend']);

        $this->postJson("/api/v1/crm/customers/{$id}/hub/actions/activate", ['confirm' => true])->assertOk();
        $this->assertSame('active', DB::table('core_licenses')->value('status'));
        $this->assertSame('trial', DB::table('crm_customer_services')->value('status'));
        $this->assertArrayNotHasKey('suspension', json_decode((string) DB::table('core_licenses')->value('meta'), true) ?: []);
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/erp-link/suspension') && $r['suspended'] === false);
        $this->assertDatabaseHas('crm_customer_audit', ['account_id' => $id, 'action' => 'account.activate']);
    }

    public function test_failed_site_suspension_is_queued_and_retried(): void
    {
        Http::fakeSequence()->push('x', 503)->push(['data' => []])->push(['data' => []])->push(['data' => ['metrics' => []]]);
        $this->admin();
        $this->postJson("/api/v1/crm/customers/{$this->acc->id}/hub/actions/suspend", ['reason' => 'r', 'confirm' => true])
            ->assertOk()->assertJsonPath('data.action_result.sites.0.ok', false);
        $this->assertSame(1, (int) DB::table('crm_customer_site_links')->value('suspension_desired'));
        $this->artisan('crm:hub-sync-sites --force')->assertSuccessful();
        $this->assertNull(DB::table('crm_customer_site_links')->value('suspension_desired'));
    }

    public function test_csv_export(): void
    {
        $this->admin();
        $res = $this->get("/api/v1/crm/customers/{$this->acc->id}/hub/export.csv")->assertOk();
        $csv = $res->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('licenses', $csv);
        $this->assertStringContainsString('C-9', $csv);
        $this->assertDatabaseHas('crm_customer_audit', ['account_id' => $this->acc->id, 'action' => 'hub.export']);
    }
}
