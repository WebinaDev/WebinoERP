<?php

namespace Modules\SiteBuilder\Support\Initialization;

use Illuminate\Support\Facades\DB;
use Modules\SiteBuilder\Entities\WebinoBusinessCategory;
use Modules\SiteBuilder\Entities\WebinoBusinessType;
use Modules\SiteBuilder\Entities\WebinoDashboardFeature;
use Modules\SiteBuilder\Entities\WebinoPackage;

final class LegacySiteTypeUpgrade
{
    public function run(): void
    {
        $category = WebinoBusinessCategory::query()->where('slug', 'site_types')->first();
        if (! $category) {
            throw new \RuntimeException('Existing site type category is missing.');
        }

        foreach ([
            ['blog', 'وبلاگ', 'Blog', false, false, true, 4],
            ['resume', 'رزومه', 'Resume', true, true, false, 10],
            ['link-hub', 'لینک‌ها', 'Links', true, false, true, 14],
        ] as [$slug, $fa, $en, $builtin, $addon, $enabled, $sort]) {
            WebinoDashboardFeature::query()->firstOrCreate(['slug' => $slug], [
                'name_fa' => $fa,
                'name_en' => $en,
                'module_slug' => $slug,
                'is_builtin' => $builtin,
                'is_addon' => $addon,
                'default_enabled' => $enabled,
                'sort_order' => $sort,
            ]);
        }

        $hub = WebinoDashboardFeature::query()->where('slug', 'link-hub')->firstOrFail();
        foreach (WebinoBusinessType::query()->pluck('id') as $typeId) {
            DB::table('webino_business_type_features')->insertOrIgnore([
                'business_type_id' => $typeId,
                'feature_id' => $hub->id,
                'is_required' => false,
                'default_selected' => false,
            ]);
        }

        $definitions = [
            'blog' => ['وبلاگ', 'Blog', 'blog-default', ['blog', 'cms', 'users', 'marketing', 'analytics']],
            'resume' => ['رزومه', 'Resume', 'resume-default', ['resume', 'cms']],
        ];

        foreach ($definitions as $slug => [$fa, $en, $theme, $modules]) {
            if (WebinoBusinessType::query()->where('category_id', $category->id)->where('slug', $slug)->exists()) {
                continue;
            }

            $features = WebinoDashboardFeature::query()->whereIn('slug', $modules)->pluck('id', 'slug');
            if ($features->count() !== count($modules)) {
                throw new \RuntimeException('Required existing site features are missing: '.$slug);
            }

            $type = WebinoBusinessType::query()->create([
                'category_id' => $category->id,
                'slug' => $slug,
                'name_fa' => $fa,
                'name_en' => $en,
                'theme_preset' => $slug,
                'default_module_slugs' => array_merge(['core'], $modules),
                'nav_preset' => ['preset' => $slug, 'active_theme_slug' => $theme],
                'sort_order' => 0,
            ]);
            $type->features()->attach($features->values()->mapWithKeys(fn ($id) => [
                $id => ['is_required' => false, 'default_selected' => true],
            ])->all());

            $package = WebinoPackage::query()->firstOrCreate(['sku' => 'pkg-'.$slug.'-starter'], [
                'name_fa' => 'بسته پایه '.$fa,
                'name_en' => $en.' Starter',
                'business_type_id' => $type->id,
                'price' => 0,
                'billing_period' => 'yearly',
            ]);
            if ($package->wasRecentlyCreated) {
                $package->features()->attach($features->values()->all());
            }
        }
    }
}
