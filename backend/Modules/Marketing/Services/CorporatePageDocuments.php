<?php

namespace Modules\Marketing\Services;

use Modules\Marketing\Entities\MarketingForm;
use Modules\Marketing\Entities\MarketingPage;

/**
 * Page-builder documents for the corporate site (home, contact, about, consultation), built from
 * builder widgets with the verbatim default content in webina-site-defaults.json (consultation:
 * the consultation brief, built from the generic content widgets + the builder form).
 *
 * apply() only fills pages that have no builder content yet; published or edited documents
 * are never overwritten (the old one-heading placeholder draft counts as empty).
 */
class CorporatePageDocuments
{
    private const TITLES = [
        'home' => ['صفحه اصلی', 'Home'],
        'contact' => ['تماس با ما', 'Contact'],
        'about' => ['درباره ما', 'About'],
        'consultation' => ['مشاوره', 'Consultation'],
    ];

    /** Marketing form (بازاریابی ← فرم‌ها) that stores consultation bookings from /consultation. */
    public const CONSULTATION_FORM = 'consultation-booking';

    private const CONSULTATION_STAGES = ['در حال راه‌اندازی', 'فعال اما نیاز به رشد', 'دارای سایت/سیستم اما نتیجه ضعیف', 'به دنبال بازطراحی کامل'];

    /** @return array<string, array> slug => document */
    public static function documents(): array
    {
        $d = SiteContentDefaults::load();

        return [
            'home' => self::home($d['home_blocks'] ?? []),
            'contact' => self::contact($d['contact_page'] ?? []),
            'about' => self::about($d['about_page']['sections'] ?? []),
            'consultation' => self::consultation(),
        ];
    }

    /** @return string[] slugs that were filled */
    public static function apply(): array
    {
        self::ensureConsultationForm();
        $filled = [];
        foreach (self::documents() as $slug => $doc) {
            if (! $doc['sections']) {
                continue;
            }
            $page = MarketingPage::query()->where('slug', $slug)->first();
            if ($page && is_array($page->builder_published) && ! empty($page->builder_published['sections'])) {
                continue;
            }
            if ($page && is_array($page->builder_draft) && ! self::isPlaceholder($page->builder_draft)) {
                continue;
            }
            $page ??= new MarketingPage(['slug' => $slug, 'title_fa' => self::TITLES[$slug][0], 'title_en' => self::TITLES[$slug][1]]);
            $page->builder_draft = $doc;
            $page->builder_published = $doc;
            $page->published = true;
            $page->status = 'published';
            $page->save();
            $filled[] = $slug;
        }

        return $filled;
    }

    public static function isPlaceholder(?array $doc): bool
    {
        $sections = $doc['sections'] ?? [];
        if (! $sections) {
            return true;
        }
        $widgets = [];
        foreach ($sections as $section) {
            foreach ($section['columns'] ?? [] as $column) {
                foreach ($column['widgets'] ?? [] as $widget) {
                    $widgets[] = $widget['id'] ?? '';
                }
            }
        }

        return $widgets === ['w_home_hero'] || $widgets === [];
    }

    private static function widget(string $id, string $type, array $props): array
    {
        return ['id' => 'w_'.$id, 'type' => $type, 'props' => $props];
    }

    private static function full(string $id, array $widget): array
    {
        return [
            'id' => 'sec_'.$id,
            'fullWidth' => true,
            'columns' => [['id' => 'col_'.$id, 'span' => 12, 'widgets' => [$widget]]],
        ];
    }

    private static function doc(array $sections): array
    {
        return ['version' => 1, 'sections' => array_values($sections), 'page_settings' => ['theme' => 'webina-corporate-v1']];
    }

    private static function home(array $blocks): array
    {
        $out = [];
        foreach ($blocks as $block) {
            if (($block['enabled'] ?? true) === false || empty($block['type'])) {
                continue;
            }
            $id = 'home_'.$block['type'];
            $out[] = self::full($id, self::widget($id, 'webina-home-'.$block['type'], $block['props'] ?? []));
        }

        return self::doc($out);
    }

    private static function slice(array $p, callable $keep): array
    {
        return array_filter($p, fn ($v, $k) => $keep((string) $k), ARRAY_FILTER_USE_BOTH);
    }

    private static function contact(array $p): array
    {
        if (! $p) {
            return self::doc([]);
        }
        $starts = fn (string ...$prefixes) => fn (string $k) => array_filter($prefixes, fn ($x) => str_starts_with($k, $x)) !== [];
        $hero = self::slice($p, $starts('hero_'));
        $hero['channels'] = array_slice($p['channels'] ?? [], 0, 2);
        $form = self::slice($p, fn ($k) => str_starts_with($k, 'form_') || in_array($k, ['submit_label', 'success_title', 'success_text', 'again_label', 'privacy_note'], true));

        return self::doc([
            self::full('contact_hero', self::widget('contact_hero', 'webina-contact-hero', $hero)),
            self::full('contact_cards', self::widget('contact_cards', 'webina-contact-cards', self::slice($p, $starts('channels', 'cop')))),
            [
                'id' => 'sec_contact_split',
                'columns' => [
                    ['id' => 'col_contact_form', 'span' => 7, 'mobileSpan' => 12, 'widgets' => [self::widget('contact_form', 'webina-contact-form', $form)]],
                    ['id' => 'col_contact_aside', 'span' => 5, 'mobileSpan' => 12, 'widgets' => [
                        self::widget('contact_hours', 'webina-contact-hours', self::slice($p, $starts('hours'))),
                        self::widget('contact_map', 'webina-contact-map', self::slice($p, $starts('map_'))),
                    ]],
                ],
            ],
            self::full('contact_socials', self::widget('contact_socials', 'webina-contact-socials', self::slice($p, $starts('social_')))),
            self::full('contact_faq', self::widget('contact_faq', 'webina-contact-faq', self::slice($p, $starts('faq_')))),
            self::full('contact_cta', self::widget('contact_cta', 'webina-cta-band', self::slice($p, $starts('cta_')))),
        ]);
    }

    private static function about(array $sections): array
    {
        $out = [];
        foreach ($sections as $s) {
            if (($s['enabled'] ?? true) === false || empty($s['type'])) {
                continue;
            }
            $id = 'about_'.$s['type'];
            $out[] = self::full($id, self::widget($id, 'webina-about-'.$s['type'], $s['props'] ?? []));
        }

        return self::doc($out);
    }

    /** Field schema of the consultation booking form (Marketing form fields JSON). */
    public static function consultationFormFields(): array
    {
        return [
            ['name' => 'name', 'label' => 'نام و نام خانوادگی', 'type' => 'text', 'required' => true],
            ['name' => 'phone', 'label' => 'شماره تماس', 'type' => 'tel', 'required' => true],
            ['name' => 'email', 'label' => 'ایمیل', 'type' => 'email', 'required' => false],
            ['name' => 'business', 'label' => 'نام کسب‌وکار / برند', 'type' => 'text', 'required' => false],
            ['name' => 'industry', 'label' => 'حوزه فعالیت', 'type' => 'text', 'required' => false],
            ['name' => 'stage', 'label' => 'وضعیت فعلی', 'type' => 'select', 'options' => self::CONSULTATION_STAGES, 'required' => false],
            ['name' => 'message', 'label' => 'توضیح کوتاه درباره نیاز یا هدف شما', 'type' => 'textarea', 'required' => false],
            ['name' => 'preferred_time', 'label' => 'زمان پیشنهادی برای جلسه', 'type' => 'text', 'required' => false],
        ];
    }

    /** Creates the booking form once; operator edits in the dashboard are kept. */
    public static function ensureConsultationForm(): void
    {
        MarketingForm::query()->firstOrCreate(
            ['slug' => self::CONSULTATION_FORM],
            [
                'title' => 'رزرو جلسه مشاوره',
                'description' => 'درخواست‌های رزرو جلسه مشاوره از صفحه /consultation',
                'fields' => self::consultationFormFields(),
                'success_message' => 'درخواست شما ثبت شد. تیم وبینا در کوتاه‌ترین زمان با شما تماس می‌گیرد و زمان جلسه را هماهنگ می‌کند.',
                'published' => true,
            ]
        );
    }

    /** Builder form widget lines: label|name|type|options|required (see frontend/src/builder/forms.ts). */
    private static function formLines(array $names): string
    {
        $lines = [];
        foreach (self::consultationFormFields() as $field) {
            if (! in_array($field['name'], $names, true)) {
                continue;
            }
            $lines[] = implode('|', [
                $field['label'],
                $field['name'],
                $field['type'],
                implode('،', $field['options'] ?? []),
                $field['required'] ? '' : 'no',
            ]);
        }

        return implode("\n", $lines);
    }

    private static function lines(array $rows): string
    {
        return implode("\n", array_map(fn (array $row) => implode('|', $row), $rows));
    }

    private static function heading(string $id, string $title, array $extra = []): array
    {
        return self::widget($id, 'section-heading', ['title' => $title, 'tag' => 'h2', 'align' => 'center'] + $extra);
    }

    private static function stack(string $id, array $widgets, array $style = []): array
    {
        $section = ['id' => 'sec_'.$id, 'columns' => [['id' => 'col_'.$id, 'span' => 12, 'widgets' => $widgets]]];
        if ($style) {
            $section['style'] = ['base' => $style];
        }

        return $section;
    }

    /** /consultation — content from the consultation brief (8 sections, verbatim copy). */
    private static function consultation(): array
    {
        $soft = ['background' => '#f5f8ff', 'radius' => '32px', 'padding' => ['top' => '56px', 'right' => '32px', 'bottom' => '56px', 'left' => '32px']];
        $softMobile = ['padding' => ['top' => '36px', 'right' => '18px', 'bottom' => '36px', 'left' => '18px'], 'radius' => '24px'];
        $form = fn (string $id, array $props) => self::widget($id, 'form', $props + [
            'formKey' => self::CONSULTATION_FORM,
            'success' => 'درخواست شما ثبت شد. تیم وبینا در کوتاه‌ترین زمان با شما تماس می‌گیرد و زمان جلسه را هماهنگ می‌کند.',
        ]);

        $hero = [
            'id' => 'sec_consult_hero',
            'style' => ['base' => $soft + ['margin' => ['top' => '24px']], 'mobile' => $softMobile],
            'columns' => [
                ['id' => 'col_consult_hero_text', 'span' => 7, 'mobileSpan' => 12, 'widgets' => [
                    self::widget('consult_hero_heading', 'section-heading', [
                        'title' => 'جلسه مشاوره تخصصی توسعه کسب‌وکار',
                        'tag' => 'h1',
                        'subtitle' => 'یک جلسه شفاف برای بررسی وضعیت فعلی کسب‌وکار شما و طراحی مسیر رشد',
                        'text' => "در این جلسه قرار نیست فقط حرف کلی بزنیم.\nوضعیت فعلی‌تان را بررسی می‌کنیم، گلوگاه‌ها را پیدا می‌کنیم و یک نقشه راه واقعی و قابل اجرا به شما می‌دهیم.",
                        'align' => 'start',
                    ]),
                    self::widget('consult_hero_badges', 'badges', [
                        'items' => self::lines([['clock', 'جلسه ۳۰ تا ۴۵ دقیقه‌ای'], ['shield', 'بدون تعهد خرید'], ['gift', 'جلسه اولیه رایگان']]),
                        'align' => 'start',
                    ]),
                    self::widget('consult_hero_button', 'button', ['label' => 'رزرو جلسه مشاوره', 'href' => '#consultation-form', 'tone' => 'pink']),
                ]],
                ['id' => 'col_consult_hero_form', 'span' => 5, 'mobileSpan' => 12, 'widgets' => [
                    $form('consult_hero_form', [
                        'title' => 'رزرو سریع جلسه',
                        'intro' => 'شماره‌تان را بگذارید تا برای هماهنگی جلسه با شما تماس بگیریم.',
                        'fields' => self::formLines(['name', 'phone', 'stage']),
                        'submit' => 'رزرو جلسه مشاوره',
                        'columns' => '1',
                    ]),
                ]],
            ],
        ];

        return self::doc([
            $hero,
            self::stack('consult_audience', [
                self::heading('consult_audience_title', 'این مشاوره برای چه کسانی طراحی شده؟'),
                self::widget('consult_audience_cards', 'icon-cards', [
                    'items' => self::lines([
                        ['rocket', 'کسب‌وکارهایی که می‌خواهند از پایه اصولی شروع کنند', ''],
                        ['chart', 'مجموعه‌هایی که سایت یا سیستم دارند اما نتیجه مطلوب نمی‌گیرند', ''],
                        ['layers', 'برندهایی که از پراکندگی پیمانکاران خسته شده‌اند', ''],
                        ['compass', 'مدیران و کارآفرینانی که به یک نقشه راه شفاف برای رشد نیاز دارند', ''],
                        ['route', 'کسانی که می‌خواهند قبل از شروع پروژه بزرگ، تصویر روشنی از مسیر داشته باشند', ''],
                    ]),
                    'columns' => '3',
                    'variant' => 'card',
                ]),
            ]),
            [
                'id' => 'sec_consult_process',
                'style' => ['base' => $soft, 'mobile' => $softMobile],
                'columns' => [['id' => 'col_consult_process', 'span' => 12, 'widgets' => [
                    self::heading('consult_process_title', 'فرآیند جلسه', ['eyebrow' => 'در جلسه مشاوره چه اتفاقی می‌افتد؟']),
                    self::widget('consult_process_steps', 'steps', [
                        'items' => self::lines([
                            ['شناخت کسب‌وکار شما', 'بررسی وضعیت فعلی، اهداف، منابع و چالش‌ها'],
                            ['تحلیل گلوگاه‌ها', 'پیدا کردن نقاط ضعف در استراتژی، طراحی، سیستم‌ها یا فروش'],
                            ['ارائه مسیر پیشنهادی', 'طراحی یک نقشه راه اولیه متناسب با شرایط شما'],
                            ['پاسخ به سوالات', 'شفاف‌سازی ابهامات و بررسی گزینه‌های همکاری'],
                        ]),
                        'layout' => 'horizontal',
                        'note' => 'جلسه حدود ۳۰ تا ۴۵ دقیقه است و بدون تعهد خرید برگزار می‌شود.',
                    ]),
                ]]],
            ],
            self::stack('consult_outcomes', [
                self::heading('consult_outcomes_title', 'خروجی جلسه مشاوره', ['eyebrow' => 'چه چیزی از این جلسه به دست می‌آورید؟']),
                self::widget('consult_outcomes_list', 'icon-cards', [
                    'items' => self::lines([
                        ['check', 'دید شفاف نسبت به وضعیت فعلی کسب‌وکار', ''],
                        ['check', 'شناسایی مهم‌ترین اولویت‌ها', ''],
                        ['check', 'یک نقشه راه اولیه و واقع‌بینانه', ''],
                        ['check', 'پیشنهاد خدمات و مسیرهای مناسب (در صورت نیاز)', ''],
                        ['check', 'پاسخ روشن به سوالات کلیدی‌تان', ''],
                    ]),
                    'columns' => '2',
                    'variant' => 'check',
                ]),
            ]),
            [
                'id' => 'sec_consult_booking',
                'style' => ['base' => $soft, 'mobile' => $softMobile],
                'columns' => [
                    ['id' => 'col_consult_booking_intro', 'span' => 4, 'mobileSpan' => 12, 'widgets' => [
                        self::widget('consult_booking_heading', 'section-heading', [
                            'title' => 'رزرو جلسه مشاوره',
                            'text' => 'پس از ثبت درخواست، تیم وبینا در کوتاه‌ترین زمان با شما تماس می‌گیرد و زمان جلسه را هماهنگ می‌کند.',
                            'align' => 'start',
                        ]),
                        self::widget('consult_booking_badges', 'badges', [
                            'items' => self::lines([['clock', 'جلسه ۳۰ تا ۴۵ دقیقه‌ای'], ['shield', 'بدون تعهد خرید'], ['message', 'آنلاین یا حضوری']]),
                            'align' => 'start',
                        ]),
                    ]],
                    ['id' => 'col_consult_booking_form', 'span' => 8, 'mobileSpan' => 12, 'widgets' => [
                        $form('consult_booking_form', [
                            'title' => 'رزرو جلسه مشاوره',
                            'fields' => self::formLines(['name', 'phone', 'email', 'business', 'industry', 'stage', 'message', 'preferred_time']),
                            'submit' => 'ثبت درخواست مشاوره',
                            'note' => 'پس از ثبت درخواست، تیم وبینا در کوتاه‌ترین زمان با شما تماس می‌گیرد و زمان جلسه را هماهنگ می‌کند.',
                            'columns' => '2',
                            'anchor' => 'consultation-form',
                        ]),
                    ]],
                ],
            ],
            self::stack('consult_trust', [
                self::heading('consult_trust_title', 'چرا این جلسه ارزشش را دارد؟'),
                self::widget('consult_trust_cards', 'icon-cards', [
                    'items' => self::lines([
                        ['target', 'جلسه کاملاً تخصصی و متمرکز روی کسب‌وکار شماست', ''],
                        ['handshake', 'بدون فشار فروش و بدون تعهد خرید برگزار می‌شود', ''],
                        ['clipboard', 'خروجی جلسه کاربردی و قابل استفاده است', ''],
                        ['idea', 'حتی اگر همکاری ادامه پیدا نکند، دید بهتری نسبت به مسیرتان پیدا می‌کنید', ''],
                    ]),
                    'columns' => '4',
                    'variant' => 'stacked',
                ]),
            ]),
            self::stack('consult_faq', [
                self::heading('consult_faq_title', 'سوالات پرتکرار'),
                self::widget('consult_faq_list', 'accordion', [
                    'items' => self::lines([
                        ['آیا جلسه رایگان است؟', 'بله، جلسه اولیه مشاوره رایگان و بدون تعهد است.'],
                        ['جلسه حضوری است یا آنلاین؟', 'معمولاً به صورت آنلاین برگزار می‌شود. در صورت نیاز امکان هماهنگی جلسه حضوری نیز وجود دارد.'],
                        ['چقدر طول می‌کشد؟', 'حدود ۳۰ تا ۴۵ دقیقه.'],
                        ['بعد از جلسه چه می‌شود؟', 'در صورت تمایل شما، پیشنهاد همکاری و نقشه راه دقیق‌تر ارائه می‌شود. هیچ اجباری وجود ندارد.'],
                    ]),
                    'firstOpen' => true,
                    'faqSchema' => true,
                ]),
            ], ['maxWidth' => '880px']),
            self::stack('consult_cta', [
                self::widget('consult_cta_banner', 'cta-banner', [
                    'title' => 'آماده‌اید وضعیت کسب‌وکارتان را شفاف ببینید؟',
                    'text' => 'همین حالا درخواست جلسه مشاوره را ثبت کنید تا مسیر رشد را با هم بررسی کنیم.',
                    'label' => 'رزرو جلسه مشاوره',
                    'href' => '#consultation-form',
                    'tone' => 'primary',
                ]),
            ]),
        ]);
    }
}
