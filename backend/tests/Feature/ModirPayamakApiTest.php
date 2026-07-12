<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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
}
