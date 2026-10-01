<?php

namespace Tests\Feature;

use Modules\Core\Entities\CoreLicense;
use Modules\Integrations\Entities\ModirPayamakAccount;
use Tests\TestCase;

class WebinocrmModirPayamakCompatTest extends TestCase
{
    public function test_dashboard_requires_domain(): void
    {
        $this->getJson('/api/webinocrm/v1/modirpayamak/dashboard')
            ->assertStatus(200)
            ->assertJsonPath('ok', false);
    }

    public function test_dashboard_returns_ok_envelope_for_licensed_domain(): void
    {
        CoreLicense::query()->create([
            'domain' => 'shop.test',
            'license_key' => 'key-test-123',
            'status' => 'active',
            'expires_at' => now()->addYear(),
        ]);

        ModirPayamakAccount::query()->create([
            'domain' => 'shop.test',
            'balance' => 42,
            'default_from' => '1000',
            'status' => 'active',
        ]);

        $this->getJson('/api/webinocrm/v1/modirpayamak/dashboard?domain=shop.test&license_key=key-test-123')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('balance', 42)
            ->assertJsonPath('account.domain', 'shop.test');
    }

    public function test_invalid_license_returns_unavailable(): void
    {
        $this->getJson('/api/webinocrm/v1/modirpayamak/dashboard?domain=shop.test&license_key=wrong')
            ->assertOk()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('unavailable', true);
    }

    public function test_secretaries_process_never_returns_silent_skipped(): void
    {
        $license = \Modules\Core\Entities\CoreLicense::query()->create([
            'license_key' => 'wb-test-phase11',
            'project_name' => 'Phase11',
            'domain' => 'phase11.example.com',
            'status' => 'active',
        ]);

        $res = $this->postJson('/api/webinocrm/v1/modirpayamak/secretaries/process', [
            'domain' => 'phase11.example.com',
            'license_key' => $license->license_key,
        ]);

        $res->assertOk();
        $this->assertNotTrue($res->json('skipped'));
        // Either real processing payload or honest unavailable
        $this->assertTrue(
            $res->json('ok') === true
            || $res->json('unavailable') === true
            || is_array($res->json('data'))
            || is_array($res->json('results'))
        );
    }

    public function test_orders_notify_never_returns_silent_skipped(): void
    {
        $license = \Modules\Core\Entities\CoreLicense::query()->create([
            'license_key' => 'wb-test-phase11-notify',
            'project_name' => 'Phase11',
            'domain' => 'phase11-notify.example.com',
            'status' => 'active',
        ]);

        $res = $this->postJson('/api/webinocrm/v1/modirpayamak/orders/notify', [
            'domain' => 'phase11-notify.example.com',
            'license_key' => $license->license_key,
            'event_key' => 'return-requested',
            'phone' => '09120000000',
        ]);

        $this->assertNotTrue($res->json('skipped'));
        $this->assertNotTrue(data_get($res->json(), 'data.skipped'));
    }

}
