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
}
