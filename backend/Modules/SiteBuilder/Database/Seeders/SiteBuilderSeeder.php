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
            ['slug' => 'catalog', 'name_fa' => 'کاتالوگ', 'name_en' => 'Catalog', 'module_slug' => 'catalog', 'sort_order' => 1],
            ['slug' => 'cart', 'name_fa' => 'سبد خرید', 'name_en' => 'Cart', 'module_slug' => 'cart', 'sort_order' => 2],
            ['slug' => 'checkout', 'name_fa' => 'پرداخت', 'name_en' => 'Checkout', 'module_slug' => 'checkout', 'sort_order' => 3],
            ['slug' => 'inventory', 'name_fa' => 'انبار', 'name_en' => 'Inventory', 'module_slug' => 'inventory', 'sort_order' => 4, 'is_addon' => true],
            ['slug' => 'marketing', 'name_fa' => 'بازاریابی', 'name_en' => 'Marketing', 'module_slug' => 'marketing', 'sort_order' => 5],
            ['slug' => 'cms', 'name_fa' => 'مدیریت محتوا', 'name_en' => 'CMS', 'module_slug' => 'cms', 'sort_order' => 6],
            ['slug' => 'blog', 'name_fa' => 'وبلاگ', 'name_en' => 'Blog', 'module_slug' => 'blog', 'sort_order' => 7],
            ['slug' => 'academy', 'name_fa' => 'آکادمی', 'name_en' => 'Academy', 'module_slug' => 'academy', 'sort_order' => 8],
            ['slug' => 'portfolio', 'name_fa' => 'نمونه‌کار', 'name_en' => 'Portfolio', 'module_slug' => 'portfolio', 'sort_order' => 9],
            ['slug' => 'announcements', 'name_fa' => 'اطلاعیه‌ها', 'name_en' => 'Announcements', 'module_slug' => 'announcements', 'sort_order' => 10],
            ['slug' => 'testimonials', 'name_fa' => 'نظرات', 'name_en' => 'Testimonials', 'module_slug' => 'testimonials', 'sort_order' => 11],
            ['slug' => 'team', 'name_fa' => 'تیم', 'name_en' => 'Team', 'module_slug' => 'team', 'sort_order' => 12],
            ['slug' => 'consultations', 'name_fa' => 'مشاوره', 'name_en' => 'Consultations', 'module_slug' => 'consultations', 'sort_order' => 13],
            ['slug' => 'analytics', 'name_fa' => 'تحلیل', 'name_en' => 'Analytics', 'module_slug' => 'analytics', 'sort_order' => 14],
            ['slug' => 'accounting', 'name_fa' => 'حسابداری', 'name_en' => 'Accounting', 'module_slug' => 'accounting', 'sort_order' => 15, 'is_addon' => true],
            ['slug' => 'rbac', 'name_fa' => 'دسترسی‌ها', 'name_en' => 'RBAC', 'module_slug' => 'rbac', 'sort_order' => 16],
        ];

        foreach ($features as $f) {
            WebinoDashboardFeature::query()->updateOrCreate(
                ['slug' => $f['slug']],
                array_merge(['default_enabled' => true, 'is_addon' => false], $f)
            );
        }

        $shop = WebinoBusinessCategory::query()->updateOrCreate(
            ['slug' => 'retail'],
            ['name_fa' => 'فروشگاهی', 'name_en' => 'Retail', 'icon' => 'ri-store-2-line', 'sort_order' => 1]
        );
        $corp = WebinoBusinessCategory::query()->updateOrCreate(
            ['slug' => 'corporate'],
            ['name_fa' => 'شرکتی', 'name_en' => 'Corporate', 'icon' => 'ri-building-line', 'sort_order' => 2]
        );
        $resume = WebinoBusinessCategory::query()->updateOrCreate(
            ['slug' => 'resume'],
            ['name_fa' => 'رزومه', 'name_en' => 'Resume', 'icon' => 'ri-profile-line', 'sort_order' => 3]
        );

        $corporateMods = ['cms', 'blog', 'academy', 'portfolio', 'announcements', 'testimonials', 'team', 'consultations', 'rbac', 'marketing'];

        $types = [
            [$shop->id, 'cosmetics', 'لوازم آرایشی', 'Cosmetics', 'cosmetics', ['catalog', 'cart', 'checkout', 'marketing', 'cms', 'blog']],
            [$shop->id, 'cafe', 'کافه و رستوران', 'Cafe & Restaurant', 'cafe', ['catalog', 'cms', 'marketing', 'blog']],
            [$shop->id, 'digital', 'کالای دیجیتال', 'Digital Goods', 'digital', ['catalog', 'cart', 'checkout', 'blog']],
            [$corp->id, 'agency', 'آژانس', 'Agency', 'agency', $corporateMods],
            [$corp->id, 'startup', 'استارتاپ', 'Startup', 'startup', array_values(array_diff($corporateMods, ['marketing']))],
            [$resume->id, 'freelancer', 'فریلنسر', 'Freelancer', 'freelancer', ['cms', 'blog']],
            [$resume->id, 'personal', 'شخصی', 'Personal', 'personal', ['cms', 'blog']],
        ];

        $featureMap = WebinoDashboardFeature::query()->pluck('id', 'slug');

        foreach ($types as [$catId, $slug, $fa, $en, $preset, $mods]) {
            $type = WebinoBusinessType::query()->updateOrCreate(
                ['category_id' => $catId, 'slug' => $slug],
                [
                    'name_fa' => $fa,
                    'name_en' => $en,
                    'theme_preset' => $preset,
                    'default_module_slugs' => array_merge(['dashboard', 'modules'], $mods),
                    'nav_preset' => ['preset' => $preset],
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
