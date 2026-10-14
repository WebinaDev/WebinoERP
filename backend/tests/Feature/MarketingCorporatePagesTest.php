<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Modules\Crm\Entities\CrmConsultation;
use Modules\Marketing\Entities\MarketingForm;
use Modules\Marketing\Entities\MarketingFormSubmission;
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
        $this->assertSame(['home', 'contact', 'about', 'consultation'], CorporatePageDocuments::apply());

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

    public function test_consultation_page_is_built_from_content_widgets(): void
    {
        CorporatePageDocuments::apply();
        $doc = MarketingPage::query()->where('slug', 'consultation')->firstOrFail()->builder_published;
        $types = $this->widgets($doc);
        foreach (['section-heading', 'badges', 'form', 'icon-cards', 'steps', 'accordion', 'cta-banner'] as $type) {
            $this->assertContains($type, $types);
        }
        $this->assertSame('جلسه مشاوره تخصصی توسعه کسب‌وکار', $doc['sections'][0]['columns'][0]['widgets'][0]['props']['title']);
        $this->assertCount(8, $doc['sections']);

        $form = MarketingForm::query()->where('slug', CorporatePageDocuments::CONSULTATION_FORM)->firstOrFail();
        $this->assertTrue($form->published);
        $this->assertSame(['name', 'phone', 'email', 'business', 'industry', 'stage', 'message', 'preferred_time'], array_column($form->fields, 'name'));

        $this->getJson('/api/v1/public/builder/pages/consultation')->assertOk()->assertJsonPath('data.slug', 'consultation');
    }

    public function test_consultation_booking_is_stored_and_opens_a_crm_consultation(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        CorporatePageDocuments::apply();
        $url = '/api/v1/public/forms/'.CorporatePageDocuments::CONSULTATION_FORM;

        $this->postJson($url, ['payload' => ['phone' => '09120000000'], '_t' => 5000])->assertStatus(422);
        $this->postJson($url, ['payload' => ['name' => 'آرش', 'phone' => '0912', 'stage' => 'نامعتبر'], '_t' => 5000])->assertStatus(422);

        $this->postJson($url, [
            'payload' => ['name' => 'آرش رضایی', 'phone' => '09120000000', 'stage' => 'فعال اما نیاز به رشد', 'business' => 'برند نمونه'],
            'page_uri' => '/consultation',
            '_hp' => '',
            '_t' => 5000,
        ])->assertCreated();

        $this->assertSame(1, MarketingFormSubmission::query()->count());
        $consultation = CrmConsultation::query()->firstOrFail();
        $this->assertSame('درخواست مشاوره: آرش رضایی', $consultation->title);
        $this->assertStringContainsString('وضعیت فعلی: فعال اما نیاز به رشد', $consultation->notes);
        $this->assertStringContainsString('/consultation', $consultation->notes);
    }

    public function test_builder_form_honeypot_is_dropped_silently(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        CorporatePageDocuments::apply();
        $url = '/api/v1/public/forms/'.CorporatePageDocuments::CONSULTATION_FORM;
        $payload = ['name' => 'bot', 'phone' => '1'];

        $this->postJson($url, ['payload' => $payload, '_hp' => 'http://spam', '_t' => 5000])->assertCreated();
        $this->postJson($url, ['payload' => $payload, '_t' => 200])->assertCreated();
        $this->assertSame(0, MarketingFormSubmission::query()->count());
        $this->assertSame(0, CrmConsultation::query()->count());
    }
}
