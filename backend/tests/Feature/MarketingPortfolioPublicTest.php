<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Marketing\Entities\MarketingPortfolioCategory;
use Modules\Marketing\Entities\MarketingPortfolioItem;
use Tests\TestCase;

class MarketingPortfolioPublicTest extends TestCase
{
    use RefreshDatabase;

    public function test_categories_and_items_filter_and_sort(): void
    {
        $web = MarketingPortfolioCategory::query()->create(['slug' => 'web', 'name' => 'وب‌سایت و فروشگاه', 'sort_order' => 1]);
        $brand = MarketingPortfolioCategory::query()->create(['slug' => 'brand', 'name' => 'هویت بصری و برند', 'sort_order' => 2]);
        MarketingPortfolioItem::query()->create(['slug' => 'a', 'title' => 'A', 'category_id' => $web->id, 'published' => true, 'published_at' => now()->subDay()]);
        MarketingPortfolioItem::query()->create(['slug' => 'b', 'title' => 'B', 'category_id' => $web->id, 'published' => true, 'featured' => true, 'published_at' => now()->subDays(5),
            'challenge' => 'c', 'solution' => 's', 'results' => 'r', 'technologies' => ['UI'], 'images' => ['/x.jpg']]);
        MarketingPortfolioItem::query()->create(['slug' => 'hidden', 'title' => 'H', 'category_id' => $brand->id, 'published' => false]);

        $this->getJson('/api/v1/public/portfolio-categories')->assertOk()
            ->assertJsonPath('data.0.slug', 'web')->assertJsonPath('data.0.items_count', 2)->assertJsonPath('data.1.items_count', 0);
        $this->getJson('/api/v1/public/portfolio')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.slug', 'a');
        $this->getJson('/api/v1/public/portfolio?sort=featured')->assertOk()->assertJsonPath('data.0.slug', 'b');
        $this->getJson('/api/v1/public/portfolio?category=brand')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/public/portfolio/b')->assertOk()->assertJsonPath('data.challenge', 'c')->assertJsonPath('data.technologies.0', 'UI');
        $this->getJson('/api/v1/public/portfolio/hidden')->assertNotFound();
    }
}
