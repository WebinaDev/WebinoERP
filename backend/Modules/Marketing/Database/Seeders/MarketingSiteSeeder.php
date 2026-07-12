<?php

namespace Modules\Marketing\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Marketing\Entities\MarketingPage;
use Modules\Marketing\Entities\MarketingService;
use Modules\Marketing\Entities\MarketingServiceCategory;
use Modules\Marketing\Entities\MarketingSiteSetting;
use Modules\Marketing\Entities\MarketingSolutionIndustry;
use Modules\Marketing\Entities\MarketingSolutionPage;

class MarketingSiteSeeder extends Seeder
{
    public function run(): void
    {
        MarketingSiteSetting::current();

        $legalPages = [
            ['slug' => 'terms', 'title_fa' => 'قوانین و مقررات', 'published' => true],
            ['slug' => 'privacy', 'title_fa' => 'حریم خصوصی', 'published' => true],
            ['slug' => 'conflict-of-interest', 'title_fa' => 'تضاد منافع', 'published' => true],
        ];
        foreach ($legalPages as $page) {
            MarketingPage::query()->firstOrCreate(
                ['slug' => $page['slug']],
                $page + ['body_fa' => '<p>محتوای این صفحه از داشبورد قابل ویرایش است.</p>']
            );
        }

        MarketingPage::query()->firstOrCreate(
            ['slug' => 'about'],
            ['title_fa' => 'درباره ما', 'published' => true, 'body_fa' => '<p>وبینا، شریک دیجیتال کسب‌وکارها.</p>']
        );

        $serviceCategories = [
            ['slug' => 'web-development', 'name' => 'توسعه وب و نرم‌افزار', 'sort_order' => 1],
            ['slug' => 'seo', 'name' => 'سئو و افزایش ترافیک', 'sort_order' => 2],
            ['slug' => 'online-ads', 'name' => 'تبلیغات آنلاین', 'sort_order' => 3],
            ['slug' => 'social-media', 'name' => 'شبکه‌های اجتماعی', 'sort_order' => 4],
            ['slug' => 'content-marketing', 'name' => 'بازاریابی محتوا', 'sort_order' => 5],
            ['slug' => 'branding-design', 'name' => 'برندینگ و طراحی', 'sort_order' => 6],
            ['slug' => 'strategy-consulting', 'name' => 'استراتژی و مشاوره', 'sort_order' => 7],
            ['slug' => 'support-infrastructure', 'name' => 'پشتیبانی و زیرساخت', 'sort_order' => 8],
        ];

        foreach ($serviceCategories as $cat) {
            $category = MarketingServiceCategory::query()->firstOrCreate(
                ['slug' => $cat['slug']],
                $cat + ['description' => "خدمات {$cat['name']}"]
            );

            MarketingService::query()->firstOrCreate(
                ['slug' => $cat['slug'].'-overview'],
                [
                    'category_id' => $category->id,
                    'title' => "مرور {$cat['name']}",
                    'excerpt' => "راهکارهای تخصصی در حوزه {$cat['name']}",
                    'body' => "<p>جزئیات خدمات {$cat['name']} را در این صفحه مشاهده کنید.</p>",
                    'published' => true,
                    'sort_order' => 1,
                ]
            );
        }

        $industries = [
            ['slug' => 'retail', 'name' => 'خرده‌فروشی', 'pages' => ['فروشگاه آنلاین', 'بازاریابی محلی', 'وفاداری مشتری']],
            ['slug' => 'fmcg', 'name' => 'FMCG', 'pages' => ['برندسازی', 'کمپین دیجیتال', 'تحلیل بازار']],
            ['slug' => 'medical', 'name' => 'پزشکی', 'pages' => ['سایت کلینیک', 'سئو پزشکی', 'مدیریت نوبت']],
            ['slug' => 'pharma', 'name' => 'دارو', 'pages' => ['اطلاع‌رسانی', 'محتوای تخصصی', 'رعایت مقررات']],
            ['slug' => 'real-estate', 'name' => 'املاک', 'pages' => ['پلتفرم آگهی', 'لیدگیری', 'تور مجازی']],
            ['slug' => 'corporate', 'name' => 'شرکتی', 'pages' => ['سایت سازمانی', 'پورتال داخلی', 'برند کارفرمایی']],
            ['slug' => 'education', 'name' => 'آموزش', 'pages' => ['LMS', 'بازاریابی دوره', 'وبینار']],
            ['slug' => 'tourism', 'name' => 'گردشگری', 'pages' => ['رزرو آنلاین', 'بازاریابی مقصد', 'شبکه‌های اجتماعی']],
        ];

        foreach ($industries as $i => $ind) {
            $industry = MarketingSolutionIndustry::query()->firstOrCreate(
                ['slug' => $ind['slug']],
                ['name' => $ind['name'], 'sort_order' => $i + 1, 'description' => "راهکارهای {$ind['name']}"]
            );

            foreach ($ind['pages'] as $j => $pageTitle) {
                $slug = \Illuminate\Support\Str::slug($pageTitle) ?: 'page-'.($j + 1);
                MarketingSolutionPage::query()->firstOrCreate(
                    ['industry_id' => $industry->id, 'slug' => $slug],
                    [
                        'title' => $pageTitle,
                        'body' => "<p>راهکار {$pageTitle} برای صنعت {$ind['name']}.</p>",
                        'published' => true,
                    ]
                );
            }
        }
    }
}
