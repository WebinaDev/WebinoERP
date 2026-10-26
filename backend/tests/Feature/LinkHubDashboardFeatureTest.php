<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SiteBuilder\Entities\WebinoDashboardFeature;
use Tests\TestCase;

class LinkHubDashboardFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_link_hub_is_in_dashboard_feature_catalog(): void
    {
        (new \Modules\SiteBuilder\Database\Seeders\SiteBuilderSeeder)->run();
        $f = WebinoDashboardFeature::query()->where('slug', 'link-hub')->first();
        $this->assertNotNull($f);
        $this->assertSame('link-hub', $f->module_slug);
        $this->assertSame('لینک‌هاب', $f->name_fa);
        $this->assertSame('ri-links-line', $f->icon);
        $this->assertTrue($f->is_addon);
    }
}
