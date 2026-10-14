<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Marketing\Services\SiteContentDefaults;
use Tests\TestCase;

class MarketingSiteContentDefaultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_run_orders_blocks_and_keeps_switches(): void
    {
        [$blocks, $branding, $social] = SiteContentDefaults::apply(
            [['type' => 'stats', 'enabled' => true], ['type' => 'why', 'enabled' => false], ['type' => 'hero', 'enabled' => true]],
            ['primary' => '#0054ff'],
            [],
        );

        $this->assertSame('hero', $blocks[0]['type']);
        $this->assertSame('وبینا | توسعه کسب‌وکار از ایده تا نتیجه', $blocks[0]['props']['title']);
        $this->assertSame('#process', $blocks[0]['props']['buttons'][1]['href']);
        $why = collect($blocks)->firstWhere('type', 'why');
        $this->assertFalse($why['enabled']);
        $this->assertSame('stats', end($blocks)['type']);
        $this->assertCount(7, collect($blocks)->firstWhere('type', 'services')['props']['items']);
        $this->assertCount(10, collect($blocks)->firstWhere('type', 'products')['props']['items']);
        $this->assertSame('#0054ff', $branding['primary']);
        $this->assertSame('021-91017441', $branding['contact']['items'][0]['value']);
        $this->assertNotEmpty($branding['header']['nav']);
        $this->assertNotEmpty($branding['footer']['quick_links']);
        $this->assertSame('https://instagram.com/WebinaAgency', $social[0]['href']);
        $this->assertSame('contact', $branding['contact_page']['form_slug']);
        $this->assertSame('tel:+982191017441', $branding['contact_page']['channels'][0]['href']);
        $this->assertStringContainsString('اندیشه', $branding['contact_page']['map_address']);
        $this->assertSame('۱۳۱۰۶', $branding['company']['registration_no']);
    }

    public function test_existing_edits_are_never_overwritten(): void
    {
        [$blocks, $branding, $social] = SiteContentDefaults::apply(
            [['type' => 'hero', 'enabled' => true, 'props' => ['title' => 'عنوان ویرایش‌شده']]],
            ['footer' => ['description' => 'متن من']],
            [['label' => 'X', 'href' => 'https://example.com']],
        );

        $this->assertSame('عنوان ویرایش‌شده', $blocks[0]['props']['title']);
        $this->assertArrayHasKey('subtitle_1', $blocks[0]['props']);
        $this->assertSame('متن من', $branding['footer']['description']);
        $this->assertArrayHasKey('quick_links', $branding['footer']);
        $this->assertSame('X', $social[0]['label']);
        $this->assertContains('about', array_column($blocks, 'type'));
    }

    public function test_contact_form_accepts_a_submission(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $this->seed(\Modules\Marketing\Database\Seeders\MarketingFormsSeeder::class);

        $this->getJson('/api/v1/public/forms/contact')->assertOk()->assertJsonPath('data.fields.0.name', 'name');
        $this->postJson('/api/v1/public/forms/contact', ['payload' => ['name' => 'آزمون', 'phone' => '09120000000']])
            ->assertStatus(422);
        $this->postJson('/api/v1/public/forms/contact', ['payload' => ['name' => 'آزمون', 'phone' => '09120000000', 'message' => 'سلام', 'topic' => 'مشاوره']])
            ->assertCreated();
    }
}
