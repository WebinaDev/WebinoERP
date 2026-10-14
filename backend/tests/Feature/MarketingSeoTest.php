<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Entities\SystemModule;
use Modules\Marketing\Entities\MarketingBlogPost;
use Modules\Marketing\Entities\MarketingPage;
use Modules\Marketing\Entities\MarketingSiteSetting;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class MarketingSeoTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        SystemModule::query()->updateOrCreate(['slug' => 'marketing'], ['name' => 'M', 'is_active' => true]);
        Sanctum::actingAs($this->actingAsRole('system_manager'));
    }

    public function test_entity_seo_is_saved_public_and_noindex_leaves_sitemap(): void
    {
        $post = MarketingBlogPost::query()->create(['slug' => 'a', 'title' => 'A', 'status' => 'published', 'published_at' => now()->subDay()]);
        MarketingBlogPost::query()->create(['slug' => 'b', 'title' => 'B', 'status' => 'published', 'published_at' => now()->subDay()]);
        MarketingBlogPost::query()->create(['slug' => 'draft', 'title' => 'D', 'status' => 'draft']);
        $this->putJson("/api/v1/marketing/blog/{$post->id}", ['seo' => ['title' => 'عنوان سئو', 'noindex' => true]])->assertOk();
        $this->getJson('/api/v1/public/blog/a')->assertOk()->assertJsonPath('data.seo.title', 'عنوان سئو');

        $paths = collect($this->getJson('/api/v1/public/sitemap')->assertOk()->json('data'))->pluck('path');
        $this->assertTrue($paths->contains('/blog/b'));
        $this->assertFalse($paths->contains('/blog/a'));
        $this->assertFalse($paths->contains('/blog/draft'));
        $this->assertTrue($paths->contains('/'));
    }

    public function test_static_route_seo_and_legacy_page_meta_merge(): void
    {
        $this->getJson('/api/v1/marketing/seo-routes')->assertOk()->assertJsonPath('data.0.path', '/');
        $this->putJson('/api/v1/marketing/seo-routes/7', ['title' => 'خدمات وبینا', 'description' => 'd'])->assertOk()->assertJsonPath('data.path', '/team');
        $branding = MarketingSiteSetting::current()->fresh()->branding;
        $this->assertSame('خدمات وبینا', $branding['seo_routes']['/team']['title']);
        $this->getJson('/api/v1/public/site')->assertOk()->assertJsonPath('data.branding.seo_routes./team.description', 'd');

        $page = MarketingPage::query()->create(['slug' => 'legacy', 'title_fa' => 'L', 'body_fa' => '<p>x</p>', 'published' => true, 'status' => 'published', 'meta' => ['wp' => 7]]);
        $this->putJson("/api/v1/marketing/pages/{$page->id}/seo", ['seo' => ['title' => 'T']])->assertOk();
        $fresh = $page->fresh();
        $this->assertSame(7, $fresh->meta['wp']);
        $this->assertSame('T', $fresh->meta['seo']['title']);
        $this->assertSame('<p>x</p>', $fresh->body_fa);
        $this->assertTrue(collect($this->getJson('/api/v1/public/sitemap')->json('data'))->pluck('path')->contains('/pages/legacy'));
    }
}
