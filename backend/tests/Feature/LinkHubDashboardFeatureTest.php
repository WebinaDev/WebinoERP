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
        $this->assertFalse($f->is_addon);
        $this->assertTrue($f->is_builtin);
        $r = WebinoDashboardFeature::query()->where('slug', 'resume')->first();
        $this->assertTrue($r->is_builtin);
        $this->assertTrue($r->is_addon);
        $this->assertFalse($r->default_enabled);
    }

    public function test_link_hub_default_on_every_site_type(): void
    {
        (new \Modules\SiteBuilder\Database\Seeders\SiteBuilderSeeder)->run();
        $types = \Modules\SiteBuilder\Entities\WebinoBusinessType::query()->with('features')->get();
        $this->assertCount(6, $types);
        foreach ($types as $t) {
            $this->assertContains('link-hub', $t->default_module_slugs);
            $this->assertTrue((bool) $t->features->firstWhere('slug', 'link-hub')?->pivot->default_selected);
        }
    }

    public function test_builtin_modules_are_detected(): void
    {
        $this->assertTrue(\Modules\SiteBuilder\Services\ModuleInstallOrchestrator::isBuiltin('link-hub'));
        $this->assertTrue(\Modules\SiteBuilder\Services\ModuleInstallOrchestrator::isBuiltin('resume'));
        $this->assertFalse(\Modules\SiteBuilder\Services\ModuleInstallOrchestrator::isBuiltin('commerce'));
    }
}
