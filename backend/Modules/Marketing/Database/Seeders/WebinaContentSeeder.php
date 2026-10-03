<?php

namespace Modules\Marketing\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Marketing\Entities\MarketingFaqItem;
use Modules\Marketing\Entities\MarketingForm;
use Modules\Marketing\Entities\MarketingMenu;
use Modules\Marketing\Entities\MarketingMenuItem;
use Modules\Marketing\Entities\MarketingPage;
use Modules\Marketing\Entities\MarketingPortfolioCategory;
use Modules\Marketing\Entities\MarketingPortfolioItem;
use Modules\Marketing\Entities\MarketingService;
use Modules\Marketing\Entities\MarketingServiceCategory;
use Modules\Marketing\Entities\MarketingSolutionIndustry;
use Modules\Marketing\Entities\MarketingSolutionPage;
use Modules\Marketing\Entities\MarketingTeamMember;
use Modules\Marketing\Entities\MarketingTestimonial;

/**
 * Company-site content aligned with Website-SiteMap-Complete.md and
 * frontend site-nav (the WebinoDocs xmind structure already in this repo).
 * WebinoDocs itself was not readable from this environment.
 */
class WebinaContentSeeder extends Seeder
{
    public function run(): void
    {
        $this->services();
        $this->solutions();
        $this->portfolio();
        $this->menus();
        $this->forms();
        $this->company();
        $this->homeDraft();
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

    private function menus(): void
    {
        $header = MarketingMenu::query()->firstOrCreate(
            ['location' => 'header'],
            ['name' => 'منوی اصلی', 'published' => true]
        );
        $footer = MarketingMenu::query()->firstOrCreate(
            ['location' => 'footer'],
            ['name' => 'فوتر', 'published' => true]
        );
        $this->item($header->id, null, 'خدمات', '/services', 1, [
            ['فناوری', '/services/tech'],
            ['رشد', '/services/growth'],
            ['برند', '/services/branding'],
            ['استراتژی', '/services/strategy'],
            ['پشتیبانی', '/services/support'],
        ]);
        $this->item($header->id, null, 'راهکارها', '/solutions', 2, [
            ['خرده‌فروشی', '/solutions/retail'],
            ['سلامت', '/solutions/health'],
            ['سازمانی', '/solutions/corporate'],
            ['آموزش', '/solutions/education'],
            ['مهمان‌نوازی', '/solutions/hospitality'],
        ]);
        $this->item($header->id, null, 'نمونه‌کارها', '/portfolio', 3);
        $this->item($header->id, null, 'منابع', '/blog', 4, [
            ['بلاگ', '/blog'],
            ['آکادمی', '/academy'],
            ['مجله', '/magazine'],
            ['دانلودها', '/downloads'],
            ['سوالات', '/faq'],
        ]);
        $this->item($header->id, null, 'شرکت', '/about', 5, [
            ['درباره', '/about'],
            ['تیم', '/team'],
            ['همکاری', '/cooperation'],
            ['تماس', '/contact'],
            ['مشاوره', '/consultation'],
            ['پروپوزال', '/proposal'],
        ]);
        $this->item($footer->id, null, 'حریم خصوصی', '/pages/privacy', 1);
        $this->item($footer->id, null, 'قوانین', '/pages/terms', 2);
    }

    /** @param  list<array{0: string, 1: string}>  $children */
    private function item(int $menuId, ?int $parentId, string $label, string $href, int $sort, array $children = []): void
    {
        $query = MarketingMenuItem::query()->where('menu_id', $menuId)->where('href', $href);
        if ($parentId === null) {
            $query->whereNull('parent_id');
        } else {
            $query->where('parent_id', $parentId);
        }
        $row = $query->first();
        if (! $row) {
            $row = MarketingMenuItem::query()->create([
                'menu_id' => $menuId,
                'parent_id' => $parentId,
                'label' => $label,
                'href' => $href,
                'sort_order' => $sort,
                'published' => true,
            ]);
        }
        foreach ($children as $i => [$childLabel, $childHref]) {
            $this->item($menuId, $row->id, $childLabel, $childHref, $i + 1);
        }
    }

    private function forms(): void
    {
        $fields = [
            ['name' => 'name', 'label' => 'نام', 'type' => 'text', 'required' => true],
            ['name' => 'phone', 'label' => 'تلفن', 'type' => 'tel', 'required' => true],
            ['name' => 'email', 'label' => 'ایمیل', 'type' => 'email', 'required' => false],
            ['name' => 'message', 'label' => 'شرح نیاز', 'type' => 'textarea', 'required' => true],
        ];
        foreach ([
            'consultation' => 'فرم مشاوره',
            'proposal' => 'درخواست پروپوزال',
            'newsletter' => 'خبرنامه',
        ] as $slug => $title) {
            MarketingForm::query()->firstOrCreate(
                ['slug' => $slug],
                [
                    'title' => $title,
                    'description' => $title.' از سایت وبینا',
                    'fields' => $slug === 'newsletter'
                        ? [['name' => 'email', 'label' => 'ایمیل', 'type' => 'email', 'required' => true]]
                        : $fields,
                    'success_message' => 'پیام شما ثبت شد. به‌زودی برمی‌گردیم.',
                    'published' => true,
                ]
            );
        }
    }

    private function company(): void
    {
        MarketingPage::query()->firstOrCreate(
            ['slug' => 'cooperation'],
            ['title_fa' => 'همکاری', 'title_en' => 'Cooperation', 'published' => true, 'status' => 'published', 'body_fa' => '<p>همکاری با تیم‌های محصول، آژانس‌ها و واحدهای داخلی سازمان.</p>']
        );
        foreach ([
            ['q' => 'پروژه از کجا شروع می‌شود؟', 'a' => 'با یک جلسه کشف رایگان. خروجی‌اش دامنه، زمان و معیار موفقیت است.'],
            ['q' => 'آیا محتوا از داشبورد قابل ویرایش است؟', 'a' => 'بله. برگه‌ها، نمونه‌کار، منو، فرم، راهکار و پوسته‌ساز در ERP ویرایش می‌شوند.'],
            ['q' => 'زبان سایت چیست؟', 'a' => 'فارسی پیش‌فرض است و چیدمان RTL دارد. انگلیسی از سوییچ زبان فعال می‌شود.'],
        ] as $i => $faq) {
            MarketingFaqItem::query()->firstOrCreate(
                ['question' => $faq['q']],
                ['answer' => $faq['a'], 'group' => 'home', 'sort_order' => $i, 'published' => true]
            );
        }
        MarketingTeamMember::query()->firstOrCreate(
            ['name' => 'تیم محصول وبینا'],
            ['role' => 'استراتژی، طراحی و مهندسی', 'bio' => 'یک تیم کوچک که از بوم کسب‌وکار تا انتشار را با هم جلو می‌برد.', 'published' => true, 'sort_order' => 1]
        );
        MarketingTestimonial::query()->firstOrCreate(
            ['author' => 'نسیم راد', 'company' => 'کلینیک آرورا'],
            ['role' => 'مدیر', 'quote' => 'مسیر نوبت‌دهی بالاخره به اندازه خود درمان جدی گرفته شد.', 'rating' => 5, 'published' => true, 'sort_order' => 1]
        );
    }

    private function homeDraft(): void
    {
        MarketingPage::query()->firstOrCreate(
            ['slug' => 'home'],
            [
                'title_fa' => 'خانه وبینا',
                'title_en' => 'Webina home',
                'published' => false,
                'status' => 'draft',
                'body_fa' => '<p>پیش‌نویس صفحه‌ساز. تا وقتی منتشر نشود، صفحه اصلی طراحی‌شده سایت نشان داده می‌شود.</p>',
                'builder_draft' => [
                    'version' => 1,
                    'sections' => [[
                        'id' => 'sec_home_hero',
                        'columns' => [[
                            'id' => 'col_home_hero',
                            'span' => 12,
                            'widgets' => [[
                                'id' => 'w_home_hero',
                                'type' => 'heading',
                                'props' => ['text' => 'کسب‌وکار شما را به سطح بعدی می‌بریم', 'tag' => 'h1'],
                            ]],
                        ]],
                    ]],
                ],
            ]
        );
    }
}
