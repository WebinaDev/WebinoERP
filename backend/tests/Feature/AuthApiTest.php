<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Database\Seeders\RolesAndPermissionsSeeder;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_login_returns_token_for_valid_user(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('secret'),
            'is_active' => true,
        ]);
        $user->assignRole('system_manager');

        $response = $this->postJson('/api/v1/core/auth/login', [
            'email' => 'test@example.com',
            'password' => 'secret',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.user.email', 'test@example.com');
        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_protected_navigation_requires_bearer_token(): void
    {
        $this->getJson('/api/v1/core/navigation')->assertStatus(401);
    }
}
