<?php

namespace Tests\Feature;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthRefreshTwoFactorTest extends TestCase
{
    public function test_refresh_preserves_two_factor_pending_ability(): void
    {
        $user = User::factory()->create([
            'two_factor_secret' => encrypt('secret'),
            'two_factor_confirmed_at' => now(),
        ]);

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
