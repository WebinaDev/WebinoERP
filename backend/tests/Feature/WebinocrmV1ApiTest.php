<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Accounting\Entities\AccProduct;
use Modules\Accounting\Entities\AccWarehouse;
use Modules\Accounting\Entities\AccWarehouseDocument;
use Modules\Core\Entities\CoreLicense;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class WebinocrmV1ApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_warehouses_support_pagination_search_and_legacy_envelope(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        AccWarehouse::create(['name' => 'Main', 'code' => 'WH-1', 'location' => 'Tehran']);
        AccWarehouse::create(['name' => 'Secondary', 'code' => 'WH-2', 'location' => 'Tabriz']);

        $this->getJson('/api/webinocrm/v1/warehouses?per_page=1&page=1')
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/webinocrm/v1/warehouses?search=Tabriz')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.name', 'Secondary');

        $this->getJson('/api/webinocrm/v1/warehouses?legacy=1')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['items', 'total']]);
    }

    public function test_warehouse_crud_with_legacy_fields(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $create = $this->postJson('/api/webinocrm/v1/warehouses/create', [
            'name' => 'Legacy WH',
            'code' => 'L-1',
            'description' => 'Test warehouse',
            'location' => 'Isfahan',
        ]);
        $create->assertCreated();
        $id = $create->json('data.id');

        $this->postJson('/api/webinocrm/v1/warehouses/update', [
            'id' => $id,
            'description' => 'Updated',
        ])->assertOk();

        $row = AccWarehouse::query()->findOrFail($id);
        $this->assertSame('L-1', $row->code);
        $this->assertSame('Isfahan', $row->location);
        $this->assertSame('Isfahan', $row->address);

        $this->postJson('/api/webinocrm/v1/warehouses/delete', ['id' => $id])
            ->assertOk();
    }

    public function test_warehouse_routes_smoke(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $warehouse = AccWarehouse::create(['name' => 'WH']);
        $product = AccProduct::create(['name' => 'Item']);

        $routes = [
            ['GET', '/api/webinocrm/v1/warehouses'],
            ['GET', '/api/webinocrm/v1/products'],
            ['GET', '/api/webinocrm/v1/warehouse/stock'],
            ['GET', "/api/webinocrm/v1/warehouse/stock/{$warehouse->id}/{$product->id}"],
            ['GET', '/api/webinocrm/v1/warehouse/outbound'],
            ['GET', '/api/webinocrm/v1/warehouse/inbound'],
            ['GET', '/api/webinocrm/v1/warehouse/audit'],
        ];

        foreach ($routes as [$method, $uri]) {
            $this->json($method, $uri)->assertOk();
        }

        $outbound = AccWarehouseDocument::create([
            'type' => 'outbound',
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
            'items' => [],
        ]);
        $inbound = AccWarehouseDocument::create([
            'type' => 'inbound',
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
            'items' => [],
        ]);
        $audit = AccWarehouseDocument::create([
            'type' => 'audit',
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
            'items' => [],
        ]);

        $this->getJson("/api/webinocrm/v1/warehouse/outbound/{$outbound->id}")->assertOk();
        $this->getJson("/api/webinocrm/v1/warehouse/inbound/{$inbound->id}")->assertOk();
        $this->getJson("/api/webinocrm/v1/warehouse/audit/{$audit->id}")->assertOk();

        $this->postJson('/api/webinocrm/v1/warehouse/outbound/create', [
            'warehouse_id' => $warehouse->id,
        ])->assertCreated();

        $this->postJson('/api/webinocrm/v1/warehouse/inbound/create', [
            'warehouse_id' => $warehouse->id,
        ])->assertCreated();

        $this->postJson('/api/webinocrm/v1/warehouse/audit/create', [
            'warehouse_id' => $warehouse->id,
        ])->assertCreated();
    }

    public function test_license_check_allows_unsigned_domain_status(): void
    {
        // Entitlement is domain-based; HMAC is optional. Unsigned check must work.
        $this->postJson('/api/webinocrm/v1/license/check', [
            'domain' => 'example.test',
            'product' => 'webinodashboard',
        ])->assertOk()
            ->assertJsonPath('data.domain', 'example.test')
            ->assertJsonPath('data.status', 'invalid');
    }

    public function test_license_check_rejects_bad_signature_when_secret_configured(): void
    {
        config(['app.webinocrm_license_hmac_secret' => 'test-secret']);

        $this->postJson('/api/webinocrm/v1/license/check', [
            'domain' => 'example.test',
            'product' => 'webinodashboard',
            'ts' => time(),
            'signature' => 'deadbeef',
        ])->assertForbidden();
    }

    public function test_license_check_accepts_valid_signature_when_secret_configured(): void
    {
        config(['app.webinocrm_license_hmac_secret' => 'test-secret']);
        $domain = 'signed.example.test';
        $product = 'webinodashboard';
        $ts = time();
        $sig = hash_hmac('sha256', $domain.'|'.$product.'|'.$ts, 'test-secret');

        $this->postJson('/api/webinocrm/v1/license/check', [
            'domain' => $domain,
            'product' => $product,
            'ts' => $ts,
            'signature' => $sig,
        ])->assertOk()
            ->assertJsonPath('data.domain', $domain)
            ->assertJsonPath('data.status', 'invalid');
    }

    public function test_license_check_matches_webina_domain_family(): void
    {
        CoreLicense::query()->create([
            'license_key' => 'dom:bluecafe.webinaagency.ir:webinodashboard',
            'project_name' => 'Bluecafe',
            'domain' => 'bluecafe.webinaagency.ir',
            'product' => 'webinodashboard',
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'expires_at' => now()->addYear(),
            'meta' => ['modules' => ['shop']],
        ]);

        // Registered under webinaagency.ir — check via webina.dev sibling must succeed.
        $this->postJson('/api/webinocrm/v1/license/check', [
            'domain' => 'bluecafe.webina.dev',
            'product' => 'webinodashboard',
        ])->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.domain', 'bluecafe.webinaagency.ir');

        // Exact registered domain still works unsigned.
        $this->postJson('/api/webinocrm/v1/license/check', [
            'domain' => 'bluecafe.webinaagency.ir',
            'product' => 'webinodashboard',
        ])->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.valid', true);
    }

    public function test_license_check_unsigned_bluecafe_does_not_500(): void
    {
        // Regression: Dashboard posts unsigned domain+product+ts; ERP must not 500
        // (historically Spatie backup ZipArchive::CM_* fatals during bootstrap without ext-zip).
        if (! class_exists(\ZipArchive::class)) {
            $this->assertArrayNotHasKey(
                \Spatie\Backup\BackupServiceProvider::class,
                $this->app->getLoadedProviders(),
                'BackupServiceProvider must not be loaded when ZipArchive is missing'
            );
        }

        $ts = time();
        $this->postJson('/api/webinocrm/v1/license/check', [
            'domain' => 'bluecafe.webinaagency.ir',
            'product' => 'webinodashboard',
            'ts' => $ts,
        ])->assertOk()
            ->assertJsonPath('data.domain', 'bluecafe.webinaagency.ir')
            ->assertJsonPath('data.product', 'webinodashboard')
            ->assertJsonStructure(['data' => ['status', 'valid', 'domain', 'product']]);
    }

    public function test_license_check_survives_missing_product_column(): void
    {
        // If domain+product migration not applied, resolver must not SQL-error on product.
        \Illuminate\Support\Facades\Schema::table('core_licenses', function (\Illuminate\Database\Schema\Blueprint $table) {
            try {
                $table->dropUnique('core_licenses_domain_product_unique');
            } catch (\Throwable) {
            }
        });
        \Illuminate\Support\Facades\Schema::table('core_licenses', function (\Illuminate\Database\Schema\Blueprint $table) {
            if (\Illuminate\Support\Facades\Schema::hasColumn('core_licenses', 'product')) {
                $table->dropColumn('product');
            }
        });
        \Modules\Core\Entities\CoreLicense::forgetPresentColumns();

        \Modules\Core\Entities\CoreLicense::query()->create(
            \Modules\Core\Entities\CoreLicense::attributesForSchema([
                'license_key' => 'dom:bluecafe.webinaagency.ir',
                'project_name' => 'Bluecafe',
                'domain' => 'bluecafe.webinaagency.ir',
                'status' => 'active',
                'start_date' => now()->toDateString(),
                'expires_at' => now()->addYear(),
                'meta' => ['modules' => ['shop']],
            ])
        );

        $this->postJson('/api/webinocrm/v1/license/check', [
            'domain' => 'bluecafe.webinaagency.ir',
            'product' => 'webinodashboard',
            'ts' => time(),
        ])->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.valid', true);
    }
}
