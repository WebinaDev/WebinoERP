<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Marketing\Entities\MarketingPage;
use Modules\Marketing\Entities\MarketingSiteSetting;
use Tests\TestCase;

class MarketingPublicApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_site_endpoint_returns_settings(): void
    {
        MarketingSiteSetting::current();

        $response = $this->getJson('/api/v1/public/site');

        $response->assertOk()
            ->assertJsonPath('data.name', 'وبینا')
            ->assertJsonPath('data.active_theme_slug', 'webina-corporate-v1');
    }

    public function test_public_home_endpoint_returns_aggregate(): void
    {
        MarketingSiteSetting::current();

        $response = $this->getJson('/api/v1/public/home');

        $response->assertOk()
            ->assertJsonStructure(['data' => ['site', 'blocks', 'announcements', 'testimonials', 'portfolio', 'blog']]);
    }

    public function test_public_page_by_slug(): void
    {
        MarketingPage::query()->create([
            'slug' => 'terms',
            'title_fa' => 'قوانین',
            'body_fa' => '<p>test</p>',
            'published' => true,
        ]);

        $this->getJson('/api/v1/public/pages/terms')
            ->assertOk()
            ->assertJsonPath('data.slug', 'terms');
    }

    public function test_public_consultation_creates_crm_record(): void
    {
        $this->postJson('/api/v1/public/consultations', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'message' => 'Hello',
        ])->assertCreated();

        $this->assertDatabaseHas('crm_consultations', [
            'title' => 'درخواست مشاوره از سایت',
        ]);
    }
}
