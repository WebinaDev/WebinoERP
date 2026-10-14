<?php

namespace Modules\Marketing\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Marketing\Entities\MarketingAcademyCourse;
use Modules\Marketing\Entities\MarketingPortfolioCategory;
use Modules\Marketing\Entities\MarketingPortfolioItem;
use Modules\Marketing\Entities\MarketingService;
use Modules\Marketing\Entities\MarketingServiceCategory;
use Modules\Marketing\Entities\MarketingSolutionIndustry;
use Modules\Marketing\Entities\MarketingSolutionPage;

/**
 * Company-site content aligned with Website-SiteMap-Complete.md and
 * frontend site-nav (the WebinoDocs xmind structure already in this repo).
 * WebinoDocs itself was not readable from this environment.
 */
/**
 * Pages, menus and builder documents are NOT seeded: page content lives only in the database and is
 * built in the page builder. Forms are seeded by MarketingFormsSeeder.
 */
class WebinaContentSeeder extends Seeder
{
    public function run(): void
    {
        $this->services();
        $this->solutions();
        $this->portfolio();
        $this->courses();
    }

    private function services(): void
    {
        $groups = [
            'tech' => ['توسعه وب', [
                'custom-web' => 'طراحی سایت اختصاصی',
                'wordpress' => 'وردپرس پیشرفته',
                'redesign' => 'بازطراحی سایت',
                'landing' => 'لندینگ پیج',
                'ecommerce' => 'فروشگاه اینترنتی',
                'marketplace' => 'مارکت‌پلیس',
                'pwa' => 'وب‌اپلیکیشن PWA',
                'native-app' => 'اپلیکیشن موبایل',
                'ai' => 'هوش مصنوعی و اتوماسیون',
            ]],
            'growth' => ['رشد و مارکتینگ', [
                'seo' => 'سئو',
                'seo-technical' => 'سئو تکنیکال',
                'seo-local' => 'سئو محلی',
                'google-ads' => 'گوگل ادز',
                'retargeting' => 'ریتارگتینگ',
                'social' => 'شبکه‌های اجتماعی',
                'content' => 'بازاریابی محتوا',
                'copywriting' => 'کپی‌رایتینگ',
            ]],
            'branding' => ['برندینگ و دیزاین', [
                'logo' => 'طراحی لوگو',
                'brandbook' => 'برندبوک',
                'ux-research' => 'تحقیقات UX',
                'ui-web' => 'طراحی UI وب',
                'ui-mobile' => 'طراحی UI موبایل',
                'ads-graphic' => 'گرافیک تبلیغاتی',
            ]],
            'strategy' => ['استراتژی', [
                'digital-transformation' => 'تحول دیجیتال',
                'bmc' => 'بوم مدل کسب‌وکار',
                'business-plan' => 'بیزینس پلن',
                'systemize' => 'سیستم‌سازی',
                'market-research' => 'تحقیقات بازار',
                'journey-map' => 'نقشه سفر مشتری',
            ]],
            'support' => ['پشتیبانی', [
                'support-desk' => 'پشتیبانی فنی',
                'security' => 'امنیت و سخت‌سازی',
                'backup' => 'بک‌آپ و بازیابی',
                'performance' => 'سرعت و Core Web Vitals',
                'hosting' => 'هاست مدیریت‌شده',
            ]],
        ];

        $order = 1;
        foreach ($groups as $slug => [$name, $items]) {
            $category = MarketingServiceCategory::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'description' => "دسته {$name}", 'sort_order' => $order]
            );
            MarketingService::query()->firstOrCreate(
                ['slug' => $slug],
                [
                    'category_id' => $category->id,
                    'title' => $name,
                    'excerpt' => "نمای کلی {$name} در وبینا.",
                    'body' => "<p>{$name} را از کشف نیاز تا تحویل و رشد پوشش می‌دهیم. هر زیرخدمت صفحه، نمونه‌کار و فرم مشاوره خودش را دارد.</p>",
                    'published' => true,
                    'sort_order' => 0,
                ]
            );
            $i = 1;
            foreach ($items as $itemSlug => $title) {
                MarketingService::query()->firstOrCreate(
                    ['slug' => $itemSlug],
                    [
                        'category_id' => $category->id,
                        'title' => $title,
                        'excerpt' => "{$title} برای کسب‌وکارهایی که می‌خواهند دقیق و قابل اندازه‌گیری جلو بروند.",
                        'body' => "<p>فرآیند {$title}: کشف، طرح، ساخت، آزمون، انتشار و پشتیبانی. نمونه‌کارهای مرتبط در <a href=\"/portfolio\">نمونه‌کارها</a> و صنایع در <a href=\"/solutions\">راهکارها</a> آمده است.</p><p><a href=\"/consultation\">درخواست مشاوره</a></p>",
                        'published' => true,
                        'sort_order' => $i++,
                    ]
                );
            }
            $order++;
        }
    }

    private function solutions(): void
    {
        $industries = [
            'retail' => ['خرده‌فروشی', ['fashion' => 'پوشاک', 'electronics' => 'الکترونیک', 'fmcg' => 'کالاهای تندمصرف']],
            'health' => ['سلامت', ['doctors' => 'پزشکان', 'clinics' => 'کلینیک‌ها', 'pharma' => 'دارو']],
            'corporate' => ['سازمانی', ['real-estate' => 'املاک', 'legal' => 'حقوقی', 'general' => 'خدمات عمومی']],
            'education' => ['آموزش', ['institutes' => 'مؤسسات', 'instructors' => 'مدرس‌ها']],
            'hospitality' => ['مهمان‌نوازی', ['cafe' => 'کافه و رستوران', 'travel' => 'گردشگری']],
        ];
        $i = 1;
        foreach ($industries as $slug => [$name, $pages]) {
            $industry = MarketingSolutionIndustry::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'description' => "راهکارهای {$name}", 'sort_order' => $i++]
            );
            foreach ($pages as $pageSlug => $title) {
                MarketingSolutionPage::query()->firstOrCreate(
                    ['industry_id' => $industry->id, 'slug' => $pageSlug],
                    [
                        'title' => $title,
                        'body' => "<p>راهکار {$title} در صنعت {$name}: سایت، قیف جذب، محتوا و اندازه‌گیری. <a href=\"/services\">خدمات</a> و <a href=\"/portfolio\">نمونه‌کارها</a> را ببینید.</p>",
                        'published' => true,
                    ]
                );
            }
        }
    }

    private function portfolio(): void
    {
        $cats = [
            'web' => 'وب',
            'growth' => 'رشد',
            'branding' => 'برند',
        ];
        $ids = [];
        $n = 1;
        foreach ($cats as $slug => $name) {
            $row = MarketingPortfolioCategory::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'description' => "نمونه‌کارهای {$name}", 'sort_order' => $n++]
            );
            $ids[$slug] = $row->id;
        }

        $projects = [
            ['aurora-clinic', 'کلینیک آرورا', 'health', 'web', '+۱۸۰٪ نوبت آنلاین', 'نوبت‌دهی و سایت کلینیک که مسیر بیمار را کوتاه کرد.', 'Next.js, Laravel'],
            ['bazaar-lane', 'بازار لین', 'retail', 'growth', '+۴۵۰٪ فروش فصلی', 'فروشگاه و کمپین جستجو برای یک برند پوشاک.', 'SEO, Ads'],
            ['north-hold', 'هلدینگ شمال', 'corporate', 'branding', 'هویت یکپارچه ۶ برند', 'برندبوک و سایت سازمانی برای گروه شرکت‌ها.', 'Brand, UI'],
            ['darsnameh', 'درسنامه', 'education', 'web', '۳ برابر ثبت‌نام دوره', 'پلتفرم دوره و لندینگ برای یک مؤسسه آموزشی.', 'PWA, CRM'],
            ['cafe-nil', 'کافه نیل', 'hospitality', 'growth', '۴٫۸ امتیاز و ظرفیت پر', 'حضور محلی، منو و رزرواسیون برای یک کافه.', 'Local SEO'],
            ['vira-pharm', 'ویرا فارما', 'health', 'web', 'محتوای منطبق با مقررات', 'سایت اطلاع‌رسانی دارو با ساختار محتوای قابل بازبینی.', 'CMS, Compliance'],
        ];
        foreach ($projects as [$slug, $title, $industry, $cat, $metric, $summary, $tech]) {
            $industryId = MarketingSolutionIndustry::query()->where('slug', $industry)->value('id');
            MarketingPortfolioItem::query()->firstOrCreate(
                ['slug' => $slug],
                [
                    'title' => $title,
                    'description' => $summary,
                    'category_id' => $ids[$cat],
                    'industry_id' => $industryId,
                    'client' => $title,
                    'result_metric' => $metric,
                    'technologies' => array_map('trim', explode(',', $tech)),
                    'cover_url' => '/marketing/case-'.$cat.'.svg',
                    'case_study' => "<p>{$summary}</p><p>نتیجه کلیدی: {$metric}. مسیر از مصاحبه تا انتشار در داشبورد وبینا قابل ویرایش است.</p>",
                    'images' => ['/marketing/case-'.$cat.'.svg'],
                    'featured' => true,
                    'published' => true,
                    'published_at' => now(),
                ]
            );
        }
    }

    /** Homepage brief §7 — course titles verbatim; descriptions intentionally left empty. */
    private function courses(): void
    {
        $rows = [
            'sales-funnel-conversion' => 'دوره طراحی قیف فروش و افزایش نرخ تبدیل',
            'online-store-launch' => 'دوره راه‌اندازی فروشگاه اینترنتی اصولی',
            'smb-automation' => 'دوره اتوماسیون کسب‌وکارهای کوچک و متوسط',
            'startup-branding' => 'دوره برندینگ کاربردی برای استارتاپ‌ها',
            'digital-growth-management' => 'دوره مدیریت رشد دیجیتال',
        ];
        $sort = 10;
        foreach ($rows as $slug => $title) {
            MarketingAcademyCourse::query()->updateOrCreate(
                ['slug' => $slug],
                ['title' => $title, 'published' => true, 'sort_order' => $sort],
            );
            $sort += 10;
        }
    }
}
