<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Entities\SystemModule;
use Modules\Integrations\Entities\IntegrationSetting;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class SmsIntegrationApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        SystemModule::create(['name' => 'Integrations', 'slug' => 'integrations', 'is_active' => true]);
    }

    public function test_get_and_update_sms_settings(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/integrations/sms/settings')->assertOk();

        $res = $this->putJson('/api/v1/integrations/sms/settings', [
            'provider' => 'melipayamak',
            'username' => 'demo',
            'password' => 'secret',
            'sender' => '5000xxx',
        ]);
        $res->assertOk();
        $res->assertJsonPath('data.provider', 'melipayamak');
        $res->assertJsonPath('data.username', 'demo');

        $stored = IntegrationSetting::getJson('sms', 'settings', []);
        $this->assertSame('melipayamak', $stored['provider'] ?? null);
        $this->assertSame('demo', $stored['username'] ?? null);
    }

    public function test_send_with_log_provider(): void
    {
        Queue::fake();
        IntegrationSetting::putJson('sms', 'settings', ['provider' => 'log']);

        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $res = $this->postJson('/api/v1/integrations/sms/send', [
            'to' => '09121234567',
            'message' => 'Test message',
        ]);
        $res->assertOk();
        $res->assertJsonPath('data.provider', 'log');
    }

    public function test_send_queues_job_for_melipayamak(): void
    {
        Queue::fake();
        IntegrationSetting::putJson('sms', 'settings', [
            'provider' => 'melipayamak',
            'username' => 'u',
            'password' => 'p',
        ]);

        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/integrations/sms/send', [
            'to' => '09121234567',
            'message' => 'OTP code',
        ])->assertOk()->assertJsonPath('data.queued', true);

        Queue::assertPushed(\App\Jobs\SendSmsJob::class);
    }
}
