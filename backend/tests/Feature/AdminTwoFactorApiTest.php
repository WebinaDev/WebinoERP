<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Database\Seeders\RolesAndPermissionsSeeder;
use Modules\Core\Entities\SystemSetting;
use Modules\Integrations\Entities\IntegrationSetting;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class AdminTwoFactorApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_system_manager_login_does_not_require_2fa_by_default(): void
    {
        $this->makeManager();

        $response = $this->postJson('/api/v1/core/auth/login', [
            'email' => 'manager@example.com',
            'password' => 'secret',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.requires_2fa', false);
    }

    public function test_dashboard_is_reachable_for_system_manager_without_2fa(): void
    {
        $user = $this->makeManager();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/core/settings')->assertOk();
    }

    public function test_enabled_2fa_without_phone_does_not_lock_dashboard(): void
    {
        SystemSetting::set('auth_2fa_required', '1', 'auth');
        $user = $this->makeManager(['phone' => null]);

        $login = $this->postJson('/api/v1/core/auth/login', [
            'email' => 'manager@example.com',
            'password' => 'secret',
        ]);
        $login->assertOk();
        $login->assertJsonPath('data.requires_2fa', false);

        Sanctum::actingAs($user);
        $this->getJson('/api/v1/core/settings')->assertOk();
    }

    public function test_configured_2fa_blocks_privileged_routes_until_verified(): void
    {
        SystemSetting::set('auth_2fa_required', '1', 'auth');
        IntegrationSetting::putJson('sms', 'settings', ['provider' => 'melipayamak']);
        $user = $this->makeManager(['phone' => '09121234567']);

        $login = $this->postJson('/api/v1/core/auth/login', [
            'email' => 'manager@example.com',
            'password' => 'secret',
        ]);
        $login->assertOk();
        $login->assertJsonPath('data.requires_2fa', true);

        Sanctum::actingAs($user, ['2fa-pending']);
        $this->getJson('/api/v1/core/settings')
            ->assertForbidden()
            ->assertJsonPath('errors.code', '2FA_REQUIRED');

        Sanctum::actingAs($user, ['*']);
        $this->getJson('/api/v1/core/settings')->assertOk();
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function makeManager(array $attrs = []): User
    {
        $user = User::factory()->create(array_merge([
            'email' => 'manager@example.com',
            'password' => Hash::make('secret'),
            'is_active' => true,
        ], $attrs));
        $user->assignRole(RolesAndPermissionsSeeder::ROLE_SYSTEM_MANAGER);

        return $user;
    }
}
