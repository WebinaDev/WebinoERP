<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Integrations\Entities\IntegrationSetting;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class BaleBusinessApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        IntegrationSetting::putJson('bale', 'settings', [
            'bot_token' => 'test-bale-token',
            'welcome_text' => 'Hello',
        ]);
    }

    public function test_bale_settings_stats_and_webhook_url(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $this->getJson('/api/webinocrm/v1/bale/settings')
            ->assertOk()
            ->assertJsonPath('data.welcome_text', 'Hello');

        $this->getJson('/api/webinocrm/v1/bale/stats')->assertOk();

        $this->getJson('/api/webinocrm/v1/bale/webhook-url')
            ->assertOk()
            ->assertJsonStructure(['data' => ['url']]);
    }

    public function test_bale_webhook_accepts_valid_payload(): void
    {
        $payload = [
            'update_id' => 9001,
            'message' => [
                'chat' => ['id' => '12345'],
                'text' => '/start',
            ],
        ];

        $this->postJson('/api/webinocrm/v1/bale/webhook', $payload)
            ->assertOk()
            ->assertJsonPath('ok', true);
    }
}
