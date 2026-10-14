<?php

namespace Tests\Feature;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthRefreshTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    public function test_refresh_preserves_two_factor_pending_ability(): void
    {
        $user = User::factory()->create();

        $token = $user->createToken('spa', ['2fa-pending']);
        Sanctum::actingAs($user, ['2fa-pending'], 'sanctum');
        $user->withAccessToken($token->accessToken);

        $this->postJson('/api/v1/core/auth/refresh')
            ->assertOk();

        $newToken = $user->tokens()->latest('id')->first();
        $this->assertNotNull($newToken);
        $this->assertContains('2fa-pending', $newToken->abilities);
        $this->assertNotContains('*', $newToken->abilities);
    }
}
