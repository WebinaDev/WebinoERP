<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Entities\SystemModule;
use Modules\Marketing\Entities\MarketingPage;
use Tests\TestCase;

class MarketingCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SystemModule::query()->firstOrCreate(['slug' => 'marketing'], ['name' => 'Marketing', 'is_active' => true]);
    }

    public function test_authenticated_user_can_list_marketing_pages(): void
    {
        $user = User::factory()->create();
        MarketingPage::query()->create([
            'slug' => 'test-page',
            'title_fa' => 'Test',
            'published' => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/marketing/pages')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'test-page']);
    }
}
