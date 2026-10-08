<?php

namespace Modules\Marketing\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Marketing\Entities\MarketingAcademyCourse;
use Modules\Marketing\Entities\MarketingAcademyLesson;
use Modules\Marketing\Entities\MarketingAnnouncement;
use Modules\Marketing\Entities\MarketingBlogCategory;
use Modules\Marketing\Entities\MarketingBlogPost;
use Modules\Marketing\Entities\MarketingFaqItem;
use Modules\Marketing\Entities\MarketingPage;
use Modules\Marketing\Entities\MarketingPortfolioItem;
use Modules\Marketing\Entities\MarketingService;
use Modules\Marketing\Entities\MarketingServiceCategory;
use Modules\Marketing\Entities\MarketingSiteSetting;
use Modules\Marketing\Entities\MarketingSolutionIndustry;
use Modules\Marketing\Entities\MarketingSolutionPage;

class MarketingSiteSeeder extends Seeder
{
    public function run(): void
    {
        $this->settings();
        $this->pages();
        $this->services();
        $this->solutions();
        $this->portfolio();
        $this->faq();
        $this->blog();
        $this->academy();
        $this->announcement();
        $this->call(WebinaContentSeeder::class);
    }

    private function settings(): void
    {
        $settings = MarketingSiteSetting::current();
        $branding = is_array($settings->branding) ? $settings->branding : [];
        $primary = $branding['primary'] ?? null;
        // Official Webina identity: blue #0054ff, charcoal #22242a, white. Older seeded palettes
        // (the #0066FF default and the gold/cream draft) are migrated; custom colours are kept.
        if ($primary === null || in_array(strtoupper((string) $primary), ['#0066FF', '#E4C27A'], true)) {
            $branding['primary'] = '#0054ff';
            $branding['ink'] = '#22242a';
            $branding['paper'] = '#ffffff';
            $branding['foreground'] = '#22242a';
        }
        if (empty($branding['trust_badges'])) {
            $branding['trust_badges'] = [
                ['id' => 'enamad', 'label' => 'نماد اعتماد الکترونیکی', 'hint' => 'نمونه. تصویر رسمی را بعد از دریافت مجوز در تنظیمات سایت بگذارید.', 'href' => null, 'image' => null],
                ['id' => 'samandehi', 'label' => 'ساماندهی', 'hint' => 'نمونه. نشان رسمی کسب‌وکار را از داشبورد بازاریابی جایگزین کنید.', 'href' => null, 'image' => null],
                ['id' => 'ssl', 'label' => 'ارتباط امن', 'hint' => 'گواهی سایت عمومی از Caddy و Let\'s Encrypt روی دامنه تنظیم‌شده.', 'href' => null, 'image' => null],
                ['id' => 'hosting', 'label' => 'میزبانی مدیریت‌شده', 'hint' => 'سکوی Platform: سرور، داکر، دامنه و پشتیبان.', 'href' => '/products#hosting', 'image' => null],
            ];
        }
        $branding['description'] = $branding['description'] ?? 'شرکت توسعه کسب و کار وبینا.';
        $blocks = is_array($settings->home_blocks) ? $settings->home_blocks : [];
        $types = array_map(fn ($b) => $b['type'] ?? '', $blocks);
        foreach (['hero', 'stats', 'services', 'products', 'solutions', 'process', 'portfolio_teaser', 'testimonials', 'announcements', 'blog', 'consultation_cta'] as $type) {
            if (! in_array($type, $types, true)) {
                $blocks[] = ['type' => $type, 'enabled' => true];
            }
        }
        if ($settings->logo_url === null || $settings->logo_url === '') {
            $settings->logo_url = '/brand/logo.png';
        }
        if ($settings->favicon_url === null || $settings->favicon_url === '') {
            $settings->favicon_url = '/brand/favicon.png';
        }
        $settings->branding = $branding;
        $settings->home_blocks = $blocks;
        $settings->active_theme_slug = $settings->active_theme_slug ?: 'webina-corporate-v1';
        $settings->save();
    }

    private function pages(): void
    {
        $this->fillPage('about', 'درباره ما', 'About', <<<'HTML'
<p>شرکت توسعه کسب و کار وبینا خدمات دیجیتال و نرم‌افزار خودش را کنار هم عرضه می‌کند.</p>
<p>خدمات: فناوری و توسعه وب، رشد و بازاریابی، هویت برند و طراحی، استراتژی و مشاوره، پشتیبانی و زیرساخت.</p>
<p>محصول‌ها: Webino Dashboard (سایت فروشگاه، مجله، کافه، رزومه یا شرکت)، WebinoERP (سامانه ماژولار همین محصول)، مستندات API و ماژول‌ها، و سکوی میزبانی Platform برای سرور، داکر، دامنه و پشتیبان.</p>
<p>شروع همکاری از فرم مشاوره یا درخواست پروپوزال است. متن این صفحه از داشبورد بازاریابی قابل ویرایش است.</p>
HTML);
        $this->fillPage('products', 'محصول‌های وبینا', 'Webina products', <<<'HTML'
<p>چهار محصول داخل همین اکوسیستم هستند و با خدمات آژانس یکی نیستند.</p>
<h2>Webino Dashboard</h2>
<p>سایت‌ساز. نوع سایت: فروشگاه، مجله، کافه، رزومه، شرکت. از Site Builder راه می‌افتد.</p>
<h2>WebinoERP</h2>
<p>سامانه ماژولار مدیریت کسب‌وکار با API و داشبورد.</p>
<h2>مستندات</h2>
<p>راهنمای API و ماژول‌ها، از جمله سکوی میزبانی، در docs-site همین محصول.</p>
<h2>سکوی میزبانی</h2>
<p>ماژول Platform: سرور، داکر، دامنه، گواهی و پشتیبان.</p>
HTML);
        $legal = 'متن حقوقی این صفحه هنوز جایگزین نشده است. از داشبورد بازاریابی، برگه‌ها، آن را ویرایش کنید.';
        $this->fillPage('terms', 'قوانین و مقررات', 'Terms', '<p>'.$legal.'</p>');
        $this->fillPage('privacy', 'حریم خصوصی', 'Privacy', '<p>'.$legal.'</p>');
        $this->fillPage('conflict-of-interest', 'تضاد منافع', 'Conflict of interest', '<p>'.$legal.'</p>');
    }

    private function fillPage(string $slug, string $titleFa, string $titleEn, string $body): void
    {
        $page = MarketingPage::query()->firstOrCreate(
            ['slug' => $slug],
            [
                'title_fa' => $titleFa,
                'title_en' => $titleEn,
                'body_fa' => $body,
                'body_en' => $body,
                'published' => true,
            ]
        );
        $thin = [
            null,
            '',
            '<p>محتوای این صفحه از داشبورد قابل ویرایش است.</p>',
            '<p>وبینا، شریک دیجیتال کسب‌وکارها.</p>',
        ];
        if (in_array($page->body_fa, $thin, true)) {
            $page->fill([
                'title_fa' => $titleFa,
                'title_en' => $titleEn,
                'body_fa' => $body,
                'body_en' => $body,
                'published' => true,
            ])->save();
        }
    }

    private function services(): void
    {
        $tree = [
            'tech' => ['فناوری و توسعه وب', 'ساخت سایت، فروشگاه، اپ و ابزار هوش مصنوعی.', [
                'custom-web' => ['طراحی سایت اختصاصی', 'سایت اختصاصی روی استک وب، جدا از قالب آماده.'],
                'wordpress' => ['وردپرس پیشرفته', 'وردپرس با پوسته و افزونه متناسب با فروش و محتوا.'],
                'redesign' => ['بازطراحی و نوسازی', 'بازطراحی سایت فعلی، از بررسی تجربه کاربر تا انتقال.'],
                'landing' => ['صفحه فروش اختصاصی', 'صفحه فروش برای کمپین، محصول یا جمع‌آوری درخواست.'],
                'ecommerce' => ['فروشگاه اینترنتی', 'فروشگاه آنلاین با کاتالوگ، سبد و مسیر پرداخت.'],
                'marketplace' => ['ساخت بازار چندفروشنده', 'بازار چندفروشنده روی زیرساخت قابل توسعه.'],
                'pwa' => ['سایت قابل نصب روی موبایل', 'وب‌سایتی که روی موبایل مثل اپ نصب می‌شود.'],
                'native-app' => ['اپلیکیشن اندروید و آیفون', 'اپ اندروید و آیفون برای خدمات یا فروش.'],
                'ai' => ['چت‌بات و هوش مصنوعی', 'چت‌بات و اتوماسیون متصل به روند فروش و CRM.'],
            ]],
            'growth' => ['رشد و بازاریابی', 'جستجو، تبلیغات، شبکه اجتماعی و محتوا.', [
                'seo' => ['دیده شدن در جستجو و جذب ترافیک', 'برنامه دیده شدن در جستجو، از ساختار سایت تا محتوا.'],
                'seo-technical' => ['بهینه‌سازی محتوا و ساختار سایت برای جستجو', 'بهبود فنی صفحات و ساختار سایت برای موتور جستجو.'],
                'seo-local' => ['دیده شدن در جستجوی محلی', 'حضور در جستجوی محلی برای کسب‌وکار حضوری.'],
                'google-ads' => ['تبلیغات گوگل', 'کمپین تبلیغات گوگل روی کلمات با قصد خرید.'],
                'retargeting' => ['تبلیغ برای بازدیدکنندگان قبلی', 'نمایش دوباره تبلیغ به کسانی که سایت را دیده‌اند.'],
                'social' => ['شبکه‌های اجتماعی', 'مدیریت و محتوای شبکه‌های اجتماعی.'],
                'content' => ['بازاریابی محتوایی', 'برنامه محتوای بلاگ و مجله برای جذب مخاطب.'],
                'copywriting' => ['متن محصول که فروش می‌سازد', 'متن صفحه و محصول که مسیر درخواست را روشن می‌کند.'],
            ]],
            'branding' => ['هویت برند و طراحی', 'نشانه، راهنمای برند و رابط.', [
                'logo' => ['طراحی لوگو', 'طراحی نشانه و قاعده استفاده از آن.'],
                'brandbook' => ['راهنمای هویت برند', 'راهنمای رنگ، حروف و کاربرد هویت بصری.'],
                'ux-research' => ['تحقیق درباره مشتری', 'شناخت کاربر و مسیرش قبل از طراحی رابط.'],
                'ui-web' => ['طراحی رابط وب‌سایت', 'طراحی رابط صفحات وب.'],
                'ui-mobile' => ['طراحی رابط موبایل', 'طراحی رابط اپ و نمای موبایل.'],
                'ads-graphic' => ['گرافیک تبلیغاتی', 'بنر و گرافیک کمپین‌های تبلیغاتی.'],
            ]],
            'strategy' => ['استراتژی و مشاوره', 'مدل کسب‌وکار، طرح و فرآیند.', [
                'digital-transformation' => ['نوسازی فروش آنلاین کسب‌وکار', 'نوسازی مسیر فروش آنلاین و ابزارهای همراه آن.'],
                'bmc' => ['بوم مدل کسب‌وکار', 'بوم مدل کسب‌وکار برای روشن شدن ارزش، کانال و مشتری.'],
                'business-plan' => ['طرح کسب‌وکار', 'طرح کسب‌وکار قابل ارائه به تیم تصمیم‌گیر.'],
                'systemize' => ['فرآیندهای شفاف و قابل اجرا', 'تبدیل کار روزانه به فرآیند قابل تکرار.'],
                'market-research' => ['تحقیقات بازار', 'تصویر بازار و رقبا بر اساس داده در دسترس.'],
                'journey-map' => ['نقشه مسیر مشتری', 'نقشه مسیر مشتری از آشنایی تا خرید.'],
            ]],
            'support' => ['پشتیبانی و زیرساخت', 'پشتیبانی، امنیت، سرعت و میزبانی.', [
                'support-desk' => ['پشتیبانی فنی', 'پشتیبانی فنی دوره‌ای برای سایت و سرویس‌ها.'],
                'security' => ['امنیت و پیکربندی امن', 'پیکربندی امن، گواهی و سخت‌سازی سرویس.'],
                'backup' => ['پشتیبان‌گیری ابری', 'پشتیبان ابری و مسیر بازیابی.'],
                'performance' => ['سرعت سایت و امتیاز عملکرد گوگل', 'سرعت صفحات و امتیازهای عملکرد.'],
                'hosting' => ['میزبانی و سرور', 'میزبانی و سرور مدیریت‌شده از سکوی Platform وبینا.'],
            ]],
        ];

        $order = 1;
        foreach ($tree as $slug => [$name, $blurb, $services]) {
            $category = MarketingServiceCategory::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'description' => $blurb, 'sort_order' => $order]
            );
            $this->upsertService($category->id, $slug, $name, $blurb, 0);
            $i = 1;
            foreach ($services as $serviceSlug => [$title, $excerpt]) {
                $this->upsertService($category->id, $serviceSlug, $title, $excerpt, $i);
                $i++;
            }
            $order++;
        }
    }

    private function upsertService(int $categoryId, string $slug, string $title, string $excerpt, int $sort): void
    {
        $body = '<p>'.e($excerpt).'</p><p>جزئیات این خدمت از داشبورد بازاریابی قابل ویرایش است. برای شروع، فرم مشاوره یا پروپوزال را بفرستید.</p>';
        $row = MarketingService::query()->firstOrCreate(
            ['slug' => $slug],
            [
                'category_id' => $categoryId,
                'title' => $title,
                'excerpt' => $excerpt,
                'body' => $body,
                'published' => true,
                'sort_order' => $sort,
            ]
        );
        if (! $row->published || $row->category_id === null) {
            $row->fill([
                'category_id' => $categoryId,
                'published' => true,
            ])->save();
        }
    }

    private function solutions(): void
    {
        $tree = [
            'retail' => ['خرده‌فروشی', 'فروش پوشاک، کالای دیجیتال و کالاهای مصرفی روزمره.', [
                'fashion' => 'مد و پوشاک',
                'electronics' => 'کالای دیجیتال',
                'fmcg' => 'سوپرمارکت و کالاهای مصرفی روزمره',
            ]],
            'health' => ['پزشکی و سلامت', 'مطب، کلینیک و دارو یا تجهیزات.', [
                'doctors' => 'پزشکان و مطب‌ها',
                'clinics' => 'کلینیک و توریسم سلامت',
                'pharma' => 'دارو و تجهیزات',
            ]],
            'corporate' => ['خدمات شرکتی', 'املاک، خدمات حقوقی و مالی، و خدمات عمومی.', [
                'real-estate' => 'املاک و ساخت‌وساز',
                'legal' => 'حقوقی و مالی',
                'general' => 'خدمات عمومی',
            ]],
            'education' => ['آموزش', 'موسسه و مدرس.', [
                'institutes' => 'موسسات و پلتفرم آموزش آنلاین',
                'instructors' => 'مدرسین و برند شخصی',
            ]],
            'hospitality' => ['گردشگری و مهمان‌نوازی', 'کافه، رستوران، سفر و اقامت.', [
                'cafe' => 'کافه و رستوران',
                'travel' => 'سفر و اقامت',
            ]],
        ];

        $order = 1;
        foreach ($tree as $slug => [$name, $description, $pages]) {
            $industry = MarketingSolutionIndustry::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'description' => $description, 'sort_order' => $order]
            );
            foreach ($pages as $pageSlug => $title) {
                MarketingSolutionPage::query()->firstOrCreate(
                    ['industry_id' => $industry->id, 'slug' => $pageSlug],
                    [
                        'title' => $title,
                        'body' => '<p>راهکار «'.e($title).'» برای '.$name.'. ترکیب خدمات فناوری، رشد، برند و پشتیبانی را با محصول‌های وبینا (Dashboard، ERP، مستندات، میزبانی) می‌توان روی این صنعت چید. متن از داشبورد قابل ویرایش است.</p>',
                        'published' => true,
                    ]
                );
            }
            $order++;
        }
    }

    private function portfolio(): void
    {
        $items = [
            ['webino-dashboard', 'Webino Dashboard', 'سایت‌ساز وبینا با نوع فروشگاه، مجله، کافه، رزومه و شرکت.'],
            ['webino-erp', 'WebinoERP', 'سامانه ماژولار مدیریت کسب‌وکار، API-first.'],
            ['webino-docs', 'مستندات وبینا', 'راهنمای API و ماژول‌ها در docs-site همین محصول.'],
            ['webino-hosting', 'سکوی میزبانی', 'Platform: سرور، داکر، دامنه، گواهی و پشتیبان.'],
        ];
        foreach ($items as [$slug, $title, $description]) {
            MarketingPortfolioItem::query()->firstOrCreate(
                ['slug' => $slug],
                [
                    'title' => $title,
                    'description' => $description,
                    'client' => 'شرکت توسعه کسب و کار وبینا',
                    'published' => true,
                    'published_at' => now(),
                ]
            );
        }
    }

    private function faq(): void
    {
        $items = [
            ['شروع', 'همکاری از کجا شروع می‌شود؟', 'از صفحه مشاوره یا درخواست پروپوزال. فرم عمومی یک رکورد مشاوره در CRM می‌سازد.'],
            ['خدمات', 'فهرست خدمات کجاست؟', 'منوی خدمات پنج ستون دارد: فناوری، رشد، برند، استراتژی و پشتیبانی. هر مورد صفحه خودش را دارد.'],
            ['محصول', 'محصول وبینا با خدمات آژانس چه فرقی دارد؟', 'خدمات، کار تیم برای کسب‌وکار شماست. محصول‌ها نرم‌افزار خود وبینا هستند: Dashboard، ERP، مستندات و سکوی میزبانی.'],
            ['تعرفه', 'تعرفه ثابت منتشر شده است؟', 'در محتوای پیش‌فرض عدد تعرفه نیست. برآورد را از مشاوره یا پروپوزال بگیرید و در صورت نیاز همین صفحه را در داشبورد ویرایش کنید.'],
            ['محتوا', 'این متن‌ها را چه کسی عوض می‌کند؟', 'از داشبورد، بخش بازاریابی: برگه‌ها، بلاگ، خدمات، راهکارها، نمونه‌کار، سوالات و تنظیمات سایت.'],
        ];
        foreach ($items as $i => [$group, $question, $answer]) {
            MarketingFaqItem::query()->firstOrCreate(
                ['question' => $question],
                ['group' => $group, 'answer' => $answer, 'sort_order' => $i + 1, 'published' => true]
            );
        }
    }

    private function blog(): void
    {
        $category = MarketingBlogCategory::query()->firstOrCreate(
            ['slug' => 'product'],
            ['name' => 'محصول']
        );
        $posts = [
            ['webino-dashboard-site-types', 'پنج نوع سایت در Webino Dashboard', 'فروشگاه، مجله، کافه، رزومه و شرکت از Site Builder بالا می‌آیند.', '<p>Webino Dashboard از Site Builder یا مسیر راه‌اندازی وبینا ساخته می‌شود. نوع سایت یکی از این‌هاست: ecommerce، magazine، cafe، resume، corporate. هر نوع، ماژول‌های لایسنس خودش را دارد.</p>'],
            ['webinoerp-and-platform', 'WebinoERP و سکوی میزبانی', 'ERP ماژولار و Platform برای سرور، داکر و دامنه.', '<p>WebinoERP سامانه مدیریت کسب‌وکار همین محصول است. ماژول Platform نقش سکوی میزبانی را دارد: سرور SSH، داکر، دامنه، گواهی و پشتیبان. سایت عمومی شرکت از کانتینر frontend، پشت Caddy، سرو می‌شود.</p>'],
            ['where-to-edit-the-site', 'محتوای سایت شرکت را از کجا ویرایش کنیم؟', 'داشبورد بازاریابی، بدون دست زدن به قالب.', '<p>برگه‌ها، خدمات، راهکارها، بلاگ، نمونه‌کار، سوالات و نشان‌های اعتماد (فیلد trust_badges در تنظیمات) از داشبورد بازاریابی عوض می‌شوند. منوی بالای سایت به همین اسلاگ‌ها وصل است.</p>'],
        ];
        foreach ($posts as [$slug, $title, $excerpt, $body]) {
            MarketingBlogPost::query()->firstOrCreate(
                ['slug' => $slug],
                [
                    'category_id' => $category->id,
                    'title' => $title,
                    'excerpt' => $excerpt,
                    'body' => $body,
                    'status' => 'published',
                    'published_at' => now(),
                ]
            );
        }
    }

    private function academy(): void
    {
        $course = MarketingAcademyCourse::query()->firstOrCreate(
            ['slug' => 'webina-stack'],
            [
                'title' => 'آشنایی با پشته وبینا',
                'description' => 'چهار درس کوتاه درباره Dashboard، ERP، مستندات و سکوی میزبانی.',
                'published' => true,
                'sort_order' => 1,
            ]
        );
        $lessons = [
            ['dashboard', 'Webino Dashboard', 'نوع‌های سایت: فروشگاه، مجله، کافه، رزومه، شرکت.'],
            ['erp', 'WebinoERP', 'ماژول‌های کسب‌وکار و API.'],
            ['docs', 'مستندات', 'راهنمای API داخل docs-site.'],
            ['hosting', 'سکوی میزبانی', 'Platform: سرور، داکر، دامنه، پشتیبان.'],
        ];
        foreach ($lessons as $i => [$slug, $title, $content]) {
            MarketingAcademyLesson::query()->firstOrCreate(
                ['course_id' => $course->id, 'slug' => $slug],
                ['title' => $title, 'content' => '<p>'.$content.'</p>', 'sort_order' => $i + 1, 'published' => true]
            );
        }
    }

    private function announcement(): void
    {
        MarketingAnnouncement::query()->firstOrCreate(
            ['title' => 'سایت شرکت از داشبورد بازاریابی به‌روز می‌شود'],
            [
                'body' => 'متن خدمات، محصول‌ها و نشان‌های اعتماد نمونه هستند تا وقتی در داشبورد جایگزین شوند. نماد اعتماد رسمی را بعد از دریافت مجوز در تنظیمات سایت بگذارید.',
                'pinned' => true,
                'published' => true,
            ]
        );
    }
}
