<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Crm\Entities\CrmLead;
use Modules\Marketing\Entities\MarketingAnnouncement;
use Modules\Marketing\Entities\MarketingBlogCategory;
use Modules\Marketing\Entities\MarketingBlogPost;
use Modules\Marketing\Entities\MarketingDownload;
use Modules\Marketing\Entities\MarketingFaqItem;
use Modules\Marketing\Entities\MarketingMagazinePost;
use Tests\TestCase;

class MarketingKnowledgePublicTest extends TestCase
{
    use RefreshDatabase;

    public function test_blog_topics_read_time_and_related(): void
    {
        $seo = MarketingBlogCategory::query()->create(['slug' => 'seo', 'name' => 'سئو']);
        MarketingBlogPost::query()->create(['slug' => 'a', 'title' => 'A', 'category_id' => $seo->id, 'status' => 'published', 'published_at' => now()->subDay(), 'body' => str_repeat('واژه ', 450)]);
        MarketingBlogPost::query()->create(['slug' => 'b', 'title' => 'B', 'category_id' => $seo->id, 'status' => 'published', 'published_at' => now()->subDays(2), 'body' => 'x']);
        MarketingBlogPost::query()->create(['slug' => 'c', 'title' => 'C', 'status' => 'published', 'published_at' => now()->subDays(3)]);

        $this->getJson('/api/v1/public/blog-categories')->assertOk()->assertJsonPath('data.0.slug', 'seo');
        $this->getJson('/api/v1/public/blog?category=seo')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.read_minutes', 3)->assertJsonMissingPath('data.0.body');
        $this->getJson('/api/v1/public/blog/a')->assertOk()->assertJsonCount(1, 'data.related')->assertJsonPath('data.related.0.slug', 'b');
    }

    public function test_magazine_featured_first_and_issues(): void
    {
        MarketingMagazinePost::query()->create(['slug' => 'n', 'title' => 'N', 'status' => 'published', 'published_at' => now(), 'issue' => 'شماره ۲']);
        MarketingMagazinePost::query()->create(['slug' => 'f', 'title' => 'F', 'status' => 'published', 'published_at' => now()->subDays(9), 'issue' => 'شماره ۱', 'featured' => true]);

        $this->getJson('/api/v1/public/magazine')->assertOk()->assertJsonPath('data.0.slug', 'f')->assertJsonCount(2, 'meta.issues');
        $this->getJson('/api/v1/public/magazine?issue='.urlencode('شماره ۲'))->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_download_gate_creates_lead_and_never_lists_url(): void
    {
        $d = MarketingDownload::query()->create(['title' => 'چک‌لیست', 'file_type' => 'checklist', 'file_url' => 'https://x.test/f.pdf', 'published' => true, 'gated' => true]);
        MarketingDownload::query()->create(['title' => 'قالب', 'file_type' => 'template', 'published' => true]);

        $this->getJson('/api/v1/public/downloads?type=checklist')->assertOk()->assertJsonCount(1, 'data')->assertJsonMissingPath('data.0.file_url');
        $this->postJson("/api/v1/public/downloads/{$d->id}/request", [])->assertUnprocessable();
        $this->postJson("/api/v1/public/downloads/{$d->id}/request", ['name' => 'علی', 'email' => 'ali@example.com'])
            ->assertOk()->assertJsonPath('data.url', 'https://x.test/f.pdf')->assertJsonPath('data.related_url', '/consultation');
        $this->assertSame(1, CrmLead::query()->where('email', 'ali@example.com')->count());
    }

    public function test_faq_groups_and_announcement_archive(): void
    {
        MarketingFaqItem::query()->create(['group' => 'مشاوره و پروپوزال', 'question' => 'q1', 'answer' => 'a1', 'published' => true]);
        MarketingFaqItem::query()->create(['question' => 'q2', 'answer' => 'a2', 'published' => true]);
        MarketingFaqItem::query()->create(['question' => 'hidden', 'answer' => 'x', 'published' => false]);
        $this->getJson('/api/v1/public/faq')->assertOk()->assertJsonCount(2, 'meta.groups')->assertJsonCount(2, 'data');

        MarketingAnnouncement::query()->create(['slug' => 'old', 'title' => 'Old', 'type' => 'event', 'published' => true, 'ends_at' => now()->subDay()]);
        MarketingAnnouncement::query()->create(['slug' => 'now', 'title' => 'Now', 'type' => 'update', 'published' => true]);
        $this->getJson('/api/v1/public/announcements')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/public/announcements?archive=1')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/public/announcements?type=event')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/public/announcements/old')->assertOk()->assertJsonPath('data.title', 'Old');
    }
}
