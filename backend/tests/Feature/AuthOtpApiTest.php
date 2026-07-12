<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Database\Seeders\RolesAndPermissionsSeeder;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class AuthOtpApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_send_login_otp_does_not_expose_debug_code_in_testing_env(): void
    {
        $response = $this->postJson('/api/v1/core/auth/otp/send', [
            'mobile' => '09121234567',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.sent', true);
        $response->assertJsonMissingPath('data.debug_code');
    }

    public function test_verify_login_otp_with_valid_code(): void
    {
        $mobile = '09129876543';
        $code = '123456';
        Cache::put('otp_login:'.$mobile, $code, now()->addMinutes(5));

        $response = $this->postJson('/api/v1/core/auth/otp/verify', [
            'mobile' => $mobile,
            'code' => $code,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.verified', true);
        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_verify_login_otp_rejects_invalid_code(): void
    {
        Cache::put('otp_login:09120000000', '111111', now()->addMinutes(5));

        $this->postJson('/api/v1/core/auth/otp/verify', [
            'mobile' => '09120000000',
            'code' => '999999',
        ])->assertStatus(422);
    }

    public function test_send_login_otp_never_exposes_debug_code_in_production(): void
    {
        app()->detectEnvironment(fn () => 'production');
        config(['app.debug' => true]);

        $response = $this->postJson('/api/v1/core/auth/otp/send', [
            'mobile' => '09121111111',
        ]);

        $response->assertOk();
        $response->assertJsonMissingPath('data.debug_code');
    }
}
