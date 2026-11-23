<?php

namespace Modules\Marketplace\Support\Initialization;

use Modules\Marketplace\Entities\SiteTheme;

trait SiteThemeDefinitions
{
    public function run(): void
    {
        SiteTheme::query()->firstOrCreate(
            ['slug' => 'corporate-demo-v1'],
            [
                'name_fa' => 'شرکتی دمو',
                'name_en' => 'Corporate Demo',
                'preview_url' => '/placeholder.svg',
                'package_path' => 'frontend/src/themes/corporate-demo-v1',
                'business_types' => ['agency', 'startup'],
                'is_default' => true,
                'sort_order' => 1,
            ]
        );
    }
}
