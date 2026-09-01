<?php

namespace Modules\SiteBuilder\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\SiteBuilder\Entities\WebinoBusinessCategory;
use Modules\SiteBuilder\Entities\WebinoBusinessType;
use Modules\SiteBuilder\Entities\WebinoDashboardFeature;
use Modules\SiteBuilder\Entities\WebinoPackage;

class SiteBuilderSeeder extends Seeder
{
    public function run(): void
    {
        $features = [
            ['slug' => 'commerce', 'name_fa' => 'فروش', 'name_en' => 'Commerce', 'module_slug' => 'commerce', 'sort_order' => 1],
            ['slug' => 'users', 'name_fa' => 'کاربران', 'name_en' => 'Users', 'module_slug' => 'users', 'sort_order' => 2],
            ['slug' => 'cms', 'name_fa' => 'مدیریت محتوا', 'name_en' => 'CMS', 'module_slug' => 'cms', 'sort_order' => 3],
            ['slug' => 'blog', 'name_fa' => 'وبلاگ', 'name_en' => 'Blog', 'module_slug' => 'blog', 'sort_order' => 4],
            ['slug' => 'marketing', 'name_fa' => 'بازاریابی', 'name_en' => 'Marketing', 'module_slug' => 'marketing', 'sort_order' => 5],
            ['slug' => 'analytics', 'name_fa' => 'تحلیل', 'name_en' => 'Analytics', 'module_slug' => 'analytics', 'sort_order' => 6],
            ['slug' => 'magazine', 'name_fa' => 'مجله', 'name_en' => 'Magazine', 'module_slug' => 'magazine', 'sort_order' => 7],
            ['slug' => 'academy', 'name_fa' => 'آکادمی', 'name_en' => 'Academy', 'module_slug' => 'academy', 'sort_order' => 8],
            ['slug' => 'cafe', 'name_fa' => 'کافه', 'name_en' => 'Cafe', 'module_slug' => 'cafe', 'sort_order' => 9],
            ['slug' => 'resume', 'name_fa' => 'رزومه', 'name_en' => 'Resume', 'module_slug' => 'resume', 'sort_order' => 10],
            ['slug' => 'corporate', 'name_fa' => 'شرکتی', 'name_en' => 'Corporate', 'module_slug' => 'corporate', 'sort_order' => 11],
            ['slug' => 'accounting', 'name_fa' => 'حسابداری', 'name_en' => 'Accounting', 'module_slug' => 'accounting', 'sort_order' => 12, 'is_addon' => true],
        ];

        foreach ($features as $f) {
            WebinoDashboardFeature::query()->updateOrCreate(
                ['slug' => $f['slug']],
                array_merge(['default_enabled' => true, 'is_addon' => false], $f)
            );
        }

        $category = WebinoBusinessCategory::query()->updateOrCreate(
            ['slug' => 'site_types'],
            ['name_fa' => 'انواع سایت', 'name_en' => 'Site types', 'icon' => 'ri-global-line', 'sort_order' => 1]
        );

        $types = [
            ['ecommerce', 'فروشگاه اینترنتی', 'E-commerce', 'ecommerce-default', ['commerce', 'users', 'cms', 'blog', 'marketing', 'analytics']],
            ['magazine', 'مجله آموزشی', 'Educational Magazine', 'magazine-default', ['magazine', 'academy', 'cms', 'blog', 'users', 'marketing', 'analytics']],
            ['cafe', 'کافه و رستوران', 'Cafe & Restaurant', 'cafe-default', ['cafe', 'commerce', 'cms', 'marketing', 'blog', 'analytics']],
            ['resume', 'رزومه', 'Resume', 'resume-default', ['resume', 'cms']],
            ['corporate', 'شرکتی', 'Corporate', 'corporate-default', ['corporate', 'academy', 'cms', 'blog', 'users', 'marketing', 'analytics']],
        ];

        $featureMap = WebinoDashboardFeature::query()->pluck('id', 'slug');

        foreach ($types as [$slug, $fa, $en, $theme, $mods]) {
            $type = WebinoBusinessType::query()->updateOrCreate(
                ['category_id' => $category->id, 'slug' => $slug],
                [
                    'name_fa' => $fa,
                    'name_en' => $en,
                    'theme_preset' => $slug,
                    'default_module_slugs' => array_merge(['core'], $mods),
                    'nav_preset' => ['preset' => $slug, 'active_theme_slug' => $theme],
                    'sort_order' => 0,
                ]
            );

            $ids = collect($mods)->map(fn ($s) => $featureMap[$s] ?? null)->filter()->values();
            $type->features()->sync($ids->mapWithKeys(fn ($id) => [$id => ['is_required' => false, 'default_selected' => true]])->all());

            WebinoPackage::query()->updateOrCreate(
                ['sku' => 'pkg-'.$slug.'-starter'],
                [
                    'name_fa' => 'بسته پایه '.$fa,
                    'name_en' => $en.' Starter',
                    'business_type_id' => $type->id,
                    'price' => 0,
                    'billing_period' => 'yearly',
                ]
            )->features()->sync($ids->all());
        }
    }
}
