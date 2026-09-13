<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Entities\SystemModule;
use Modules\Integrations\Entities\IntegrationSetting;
use Modules\Integrations\Entities\ModirPayamakPackage;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class ModirPayamakApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        SystemModule::create(['name' => 'Integrations', 'slug' => 'integrations', 'is_active' => true]);
        IntegrationSetting::putString('modirpayamak', 'enabled', '1');
        IntegrationSetting::putString('modirpayamak', 'api_key', 'test-key');
        ModirPayamakPackage::create(['name' => 'Starter', 'amount' => 100000, 'sms_units' => 200, 'is_active' => true, 'sort_order' => 1]);
    }

    public function test_packages_and_topup_init_with_mock_edge(): void
    {
        putenv('MODIRPAYAMAK_MOCK=true');
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/integrations/modirpayamak/packages')->assertOk();

        $init = $this->postJson('/api/v1/integrations/modirpayamak/topup/init', [
            'domain' => 'client.example.com',
            'package_id' => 1,
        ]);
        $init->assertOk();

        $this->getJson('/api/v1/integrations/modirpayamak/admin/dashboard')->assertOk();
    }

    public function test_send_validation_requires_recipients(): void
    {
        putenv('MODIRPAYAMAK_MOCK=true');
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/integrations/modirpayamak/send', [
            'domain' => 'client.example.com',
            'message' => 'Hello',
            'recipients' => [],
        ])->assertStatus(422);
    }

    public function test_settings_persist_without_echoing_api_key(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/integrations/modirpayamak/settings', [
            'api_key' => 'secret-live-key-xyz',
            'default_from' => '3000500',
            'enabled' => true,
        ])->assertOk()
            ->assertJsonPath('data.has_api_key', true)
            ->assertJsonPath('data.default_from', '3000500')
            ->assertJsonPath('data.enabled', true)
            ->assertJsonMissingPath('data.api_key');

        $show = $this->getJson('/api/v1/integrations/modirpayamak/settings')
            ->assertOk()
            ->assertJsonPath('data.has_api_key', true)
            ->assertJsonPath('data.default_from', '3000500')
            ->assertJsonPath('data.enabled', true);

        $this->assertNotEmpty($show->json('data.api_key_masked'));
        $this->assertNull($show->json('data.api_key'));

        $this->putJson('/api/v1/integrations/modirpayamak/settings', [
            'default_from' => '3000501',
            'enabled' => true,
        ])->assertOk()->assertJsonPath('data.has_api_key', true);

        $this->assertSame('secret-live-key-xyz', IntegrationSetting::getString('modirpayamak', 'api_key', ''));
        $this->assertSame('3000501', IntegrationSetting::getString('modirpayamak', 'default_from', ''));
    }

    public function test_admin_edge_lists_do_not_require_domain(): void
    {
        putenv('MODIRPAYAMAK_MOCK=true');
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/integrations/modirpayamak/admin/reports/outbox')
            ->assertOk();
        $this->getJson('/api/v1/integrations/modirpayamak/admin/patterns')
            ->assertOk();
        $this->getJson('/api/v1/integrations/modirpayamak/admin/numbers')
            ->assertOk();
        $this->getJson('/api/v1/integrations/modirpayamak/admin/phonebooks')
            ->assertOk();

        $this->postJson('/api/v1/integrations/modirpayamak/admin/send', [
            'message' => 'Hello reseller',
            'recipients' => ['09120000000'],
        ])->assertOk();
    }

    public function test_admin_proxy_tickets_users_drafts_with_mock(): void
    {
        putenv('MODIRPAYAMAK_MOCK=true');
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $paths = [
            ['GET', 'api/tickets'],
            ['GET', 'api/user'],
            ['GET', 'api/drafts'],
        ];

        foreach ($paths as [$method, $path]) {
            $res = $this->postJson('/api/v1/integrations/modirpayamak/admin/proxy', [
                'method' => $method,
                'path' => $path,
            ]);
            $res->assertOk();
            $data = $res->json('data');
            $this->assertIsArray($data);
            if ($path === 'api/tickets') {
                $this->assertArrayHasKey('tickets', $data);
            }
            if ($path === 'api/user') {
                $this->assertArrayHasKey('users', $data);
            }
            if ($path === 'api/drafts') {
                $this->assertArrayHasKey('drafts', $data);
            }
        }

        $this->postJson('/api/v1/integrations/modirpayamak/admin/proxy', [
            'method' => 'POST',
            'path' => 'api/tickets',
            'body' => ['subject' => 'Test', 'message' => 'Hello'],
        ])->assertOk();

        $this->postJson('/api/v1/integrations/modirpayamak/admin/proxy', [
            'method' => 'GET',
            'path' => 'api/tickets/1',
        ])->assertOk()->assertJsonPath('data.ticket.id', 1);
    }

    public function test_admin_packages_crud_with_crm_field_aliases(): void
    {
        putenv('MODIRPAYAMAK_MOCK=true');
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $list = $this->getJson('/api/v1/integrations/modirpayamak/admin/packages')->assertOk();
        $this->assertNotEmpty($list->json('data.packages'));
        $this->assertSame(200, $list->json('data.packages.0.bonus'));

        $created = $this->postJson('/api/v1/integrations/modirpayamak/admin/packages', [
            'name' => 'Pro',
            'amount' => 250000,
            'bonus' => 500,
            'sort' => 5,
            'status' => 'active',
        ])->assertCreated();

        $id = (int) $created->json('data.id');
        $this->assertGreaterThan(0, $id);

        $this->postJson('/api/v1/integrations/modirpayamak/admin/packages', [
            'id' => $id,
            'name' => 'Pro Plus',
            'amount' => 300000,
            'bonus' => 600,
            'sort' => 6,
            'status' => 'inactive',
        ])->assertOk();

        $this->deleteJson('/api/v1/integrations/modirpayamak/admin/packages/'.$id)->assertOk();
    }

    public function test_admin_tariffs_and_secretaries_crud(): void
    {
        putenv('MODIRPAYAMAK_MOCK=true');
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/integrations/modirpayamak/admin/tariffs')
            ->assertOk()
            ->assertJsonStructure(['data' => ['tariffs', 'tax_percent', 'surcharge_rial']]);

        $saved = $this->postJson('/api/v1/integrations/modirpayamak/admin/tariffs', [
            'line_type' => 'test-line',
            'operator' => 'mci',
            'rate_fa' => 1000,
            'rate_la' => 2000,
            'sort' => 1,
            'status' => 'active',
        ])->assertOk();
        $tariffId = (int) $saved->json('data.id');

        $this->deleteJson('/api/v1/integrations/modirpayamak/admin/tariffs/'.$tariffId)->assertOk();

        $this->postJson('/api/v1/integrations/modirpayamak/admin/secretaries', [
            'domain' => 'shop.example.com',
            'type' => 'auto_reply',
            'name' => 'Welcome',
            'keywords' => '*',
            'reply_body' => 'Hi',
        ])->assertOk();

        $list = $this->getJson('/api/v1/integrations/modirpayamak/admin/secretaries?domain=shop.example.com')
            ->assertOk();
        $this->assertCount(1, $list->json('data.secretaries'));
        $ruleId = (int) $list->json('data.secretaries.0.id');

        $this->postJson('/api/v1/integrations/modirpayamak/admin/secretaries/delete', [
            'domain' => 'shop.example.com',
            'id' => $ruleId,
        ])->assertOk();
    }

    public function test_admin_numbers_attach_detach_and_pattern_registry(): void
    {
        putenv('MODIRPAYAMAK_MOCK=true');
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/integrations/modirpayamak/admin/numbers/attach', [
            'domain' => 'shop.example.com',
            'number' => '+983000505',
            'role' => 'service',
        ])->assertOk()
            ->assertJsonPath('data.number.number', '+983000505');

        $customers = $this->getJson('/api/v1/integrations/modirpayamak/admin/customers')->assertOk();
        $accounts = $customers->json('data.accounts');
        $this->assertIsArray($accounts);
        $this->assertNotEmpty($accounts[0]['numbers'] ?? []);

        $this->postJson('/api/v1/integrations/modirpayamak/admin/patterns/attach', [
            'domain' => 'shop.example.com',
            'scope' => 'order_customer',
            'event_key' => 'processing',
            'pattern_code' => 'PAT001',
        ])->assertOk();

        $registry = $this->getJson('/api/v1/integrations/modirpayamak/admin/patterns/registry?domain=shop.example.com')
            ->assertOk();
        $this->assertCount(1, $registry->json('data.registry'));

        $this->postJson('/api/v1/integrations/modirpayamak/admin/patterns/detach', [
            'domain' => 'shop.example.com',
            'scope' => 'order_customer',
            'event_key' => 'processing',
        ])->assertOk();

        $this->postJson('/api/v1/integrations/modirpayamak/admin/numbers/detach', [
            'domain' => 'shop.example.com',
            'role' => 'service',
            'number' => '+983000505',
        ])->assertOk();
    }

    public function test_admin_balance_ledger_and_local_messages(): void
    {
        putenv('MODIRPAYAMAK_MOCK=true');
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/integrations/modirpayamak/admin/customers/balance', [
            'domain' => 'shop.example.com',
            'amount' => 5000,
            'note' => 'manual topup',
        ])->assertOk();

        $ledger = $this->getJson('/api/v1/integrations/modirpayamak/admin/customers/ledger?domain=shop.example.com')
            ->assertOk();
        $this->assertNotEmpty($ledger->json('data.ledger'));

        $this->postJson('/api/v1/integrations/modirpayamak/admin/send', [
            'sending_type' => 'pattern',
            'code' => 'PAT001',
            'from_number' => '+983000505',
            'recipients' => ['09121234567'],
            'params' => ['name' => 'Ali'],
        ])->assertOk();

        $messages = $this->getJson('/api/v1/integrations/modirpayamak/admin/messages')->assertOk();
        $this->assertNotEmpty($messages->json('data.messages'));
    }
}
