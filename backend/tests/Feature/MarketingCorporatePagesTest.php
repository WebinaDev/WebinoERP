<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Marketing\Entities\MarketingPage;
use Modules\Marketing\Services\CorporatePageDocuments;
use Tests\TestCase;

class MarketingCorporatePagesTest extends TestCase
{
    use RefreshDatabase;

    private function widgets(array $doc): array
    {
        $out = [];
        foreach ($doc['sections'] as $section) {
            foreach ($section['columns'] as $column) {
                foreach ($column['widgets'] as $widget) {
                    $out[] = $widget['type'];
                }
            }
        }

        return $out;
    }

    public function test_pages_are_seeded_as_builder_documents(): void
    {
        $this->assertSame(['home', 'contact', 'about'], CorporatePageDocuments::apply());

        $home = MarketingPage::query()->where('slug', 'home')->firstOrFail();
        $this->assertSame('webina-home-hero', $this->widgets($home->builder_published)[0]);
        $this->assertCount(11, $this->widgets($home->builder_published));

        $contact = $this->widgets(MarketingPage::query()->where('slug', 'contact')->firstOrFail()->builder_published);
        $this->assertContains('webina-contact-form', $contact);
        $this->assertContains('webina-contact-map', $contact);

        $about = MarketingPage::query()->where('slug', 'about')->firstOrFail()->builder_published;
        $this->assertSame('webina-about-hero', $this->widgets($about)[0]);
        $this->assertSame('ما نتیجه‌ می‌سازیم، نه فقط خروجی', $about['sections'][0]['columns'][0]['widgets'][0]['props']['title'] ?? null);

        $this->getJson('/api/v1/public/builder/pages/contact')->assertOk()->assertJsonPath('data.slug', 'contact');
    }

    public function test_live_edits_are_never_overwritten(): void
    {
        CorporatePageDocuments::apply();
        $page = MarketingPage::query()->where('slug', 'home')->firstOrFail();
        $doc = $page->builder_published;
        $doc['sections'] = array_slice($doc['sections'], 0, 1);
        $page->builder_published = $doc;
        $page->builder_draft = $doc;
        $page->save();

        $this->assertSame([], CorporatePageDocuments::apply());
        $this->assertCount(1, $page->fresh()->builder_published['sections']);
    }

    public function test_placeholder_home_draft_is_replaced(): void
    {
        MarketingPage::query()->create([
            'slug' => 'home', 'title_fa' => 'خانه', 'published' => false, 'status' => 'draft',
            'builder_draft' => ['version' => 1, 'sections' => [['id' => 's', 'columns' => [['id' => 'c', 'span' => 12, 'widgets' => [['id' => 'w_home_hero', 'type' => 'heading', 'props' => []]]]]]]],
        ]);
        $this->assertContains('home', CorporatePageDocuments::apply());
    }
}
