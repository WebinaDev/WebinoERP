<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Marketing\Entities\MarketingPage;
use Tests\TestCase;

class WordPressImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_command_is_idempotent_with_wp_id(): void
    {
        Http::fake([
            'https://example.test/wp-json/wp/v2/pages*' => Http::response([
                [
                    'id' => 42,
                    'slug' => 'about-us',
                    'title' => ['rendered' => 'About Us'],
                    'content' => ['rendered' => '<p>About</p>'],
                    'status' => 'publish',
                ],
            ], 200),
            'https://example.test/wp-json/wp/v2/posts*' => Http::response([], 200),
        ]);

        $this->artisan('marketing:import-wordpress', ['--url' => 'https://example.test'])
            ->assertExitCode(0);

        $this->assertDatabaseHas('marketing_pages', ['wp_id' => 42, 'slug' => 'about-us']);

        $this->artisan('marketing:import-wordpress', ['--url' => 'https://example.test'])
            ->assertExitCode(0);

        $this->assertEquals(1, MarketingPage::query()->where('wp_id', 42)->count());
    }
}
