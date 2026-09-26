<?php

namespace Modules\Sales\Services;

use Modules\Sales\Entities\SalesRahnSetting;

/**
 * Defaults, taxonomy seed, get/save for نرخ‌نامه (رهن‌درصد).
 */
class RahnSettingsService
{
    /** @var list<int> */
    public const DURATION_OPTIONS = [6, 9, 12, 18, 24];

    /**
     * @return list<array<string, mixed>>
     */
    public static function defaultWizardSteps(): array
    {
        return [
            ['id' => 'business', 'type' => 'business', 'label' => 'کسب‌وکار', 'body' => '', 'sort_order' => 1, 'active' => true, 'system' => true],
            ['id' => 'topic', 'type' => 'topic', 'label' => 'موضوع', 'body' => '', 'sort_order' => 2, 'active' => true, 'system' => true],
            ['id' => 'domain', 'type' => 'domain', 'label' => 'حوزه', 'body' => '', 'sort_order' => 3, 'active' => true, 'system' => true],
            ['id' => 'services', 'type' => 'services', 'label' => 'خدمات', 'body' => '', 'sort_order' => 4, 'active' => true, 'system' => true],
            ['id' => 'deal', 'type' => 'deal', 'label' => 'تعرفه', 'body' => '', 'sort_order' => 5, 'active' => true, 'system' => true],
            ['id' => 'summary', 'type' => 'summary', 'label' => 'خلاصه', 'body' => '', 'sort_order' => 6, 'active' => true, 'system' => true],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'T' => 12,
            'm' => 0.60,
            'k' => 0.70,
            'p_min' => 0.05,
            'p_max' => 0.25,
            'p_default' => 0.10,
            's_hat_default' => 100000000,
            'duration_options' => self::DURATION_OPTIONS,
            'clause_template' => 'هر ماه {F} تومان ثابت + {p} درصد از فروش خالص در طول {T} ماه',
            'review' => [
                'enabled' => false,
                'deviation_percent' => 25,
                'consecutive_months' => 3,
            ],
            'sales_definition' => [
                'G' => ['enabled' => true, 'label' => 'فروش ثبت‌شده سایت'],
                'R' => ['enabled' => true, 'label' => 'لغو و مرجوعی قطعی'],
                'D' => ['enabled' => true, 'label' => 'کارمزد درگاه / پلتفرم / تخفیف کانال'],
                'X' => ['enabled' => true, 'label' => 'سفارش تست و فروش خارج از اسکوپ'],
            ],
            'topics' => self::seedTopics(),
            'domains' => self::seedDomains(),
            'categories' => self::seedCategories(),
            'catalog' => self::seedCatalog(),
            'wizard_steps' => self::defaultWizardSteps(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function seedTopics(): array
    {
        return [
            ['id' => 'topic_cafe', 'name' => 'کافه و رستوران', 'description' => 'کافه، رستوران، فست‌فود و کافی‌شاپ', 'sort_order' => 1, 'active' => true],
            ['id' => 'topic_retail', 'name' => 'فروشگاهی', 'description' => 'خرده‌فروشی آنلاین و حضوری', 'sort_order' => 2, 'active' => true],
            ['id' => 'topic_service', 'name' => 'خدماتی', 'description' => 'خدمات حرفه‌ای B2C و B2B', 'sort_order' => 3, 'active' => true],
            ['id' => 'topic_company', 'name' => 'شرکت و سازمانی', 'description' => 'شرکت‌ها، برندها و مجموعه‌های بزرگ‌تر', 'sort_order' => 4, 'active' => true],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function seedDomains(): array
    {
        return [
            ['id' => 'dom_coffee', 'topic_id' => 'topic_cafe', 'name' => 'فروش قهوه و کافه', 'description' => 'منو، سفارش آنلاین، میز و پیک', 'p_suggest' => 0.12, 'sort_order' => 1, 'active' => true],
            ['id' => 'dom_restaurant', 'topic_id' => 'topic_cafe', 'name' => 'رستوران و فست‌فود', 'description' => 'سفارش غذا و رزرو', 'p_suggest' => 0.11, 'sort_order' => 2, 'active' => true],
            ['id' => 'dom_cosmetics', 'topic_id' => 'topic_retail', 'name' => 'لوازم آرایشی و بهداشتی', 'description' => 'خرده‌فروشی با حاشیه بالاتر — درصد پیشنهادی بالاتر', 'p_suggest' => 0.18, 'sort_order' => 1, 'active' => true],
            ['id' => 'dom_fashion', 'topic_id' => 'topic_retail', 'name' => 'مد و پوشاک', 'description' => 'لباس، کفش و اکسسوری', 'p_suggest' => 0.14, 'sort_order' => 2, 'active' => true],
            ['id' => 'dom_gold', 'topic_id' => 'topic_retail', 'name' => 'طلا و جواهر', 'description' => 'حاشیه محدود — درصد پیشنهادی پایین‌تر', 'p_suggest' => 0.06, 'sort_order' => 3, 'active' => true],
            ['id' => 'dom_electronics', 'topic_id' => 'topic_retail', 'name' => 'موبایل و الکترونیک', 'description' => 'کالای رقابتی با حاشیه متغیر', 'p_suggest' => 0.08, 'sort_order' => 4, 'active' => true],
            ['id' => 'dom_clinic', 'topic_id' => 'topic_service', 'name' => 'کلینیک و زیبایی', 'description' => 'نوبت‌دهی و خدمات زیبایی', 'p_suggest' => 0.13, 'sort_order' => 1, 'active' => true],
            ['id' => 'dom_education', 'topic_id' => 'topic_service', 'name' => 'آموزش و دوره', 'description' => 'دوره آنلاین و حضوری', 'p_suggest' => 0.15, 'sort_order' => 2, 'active' => true],
            ['id' => 'dom_agency', 'topic_id' => 'topic_company', 'name' => 'آژانس و برند سازمانی', 'description' => 'سایت شرکتی و لید', 'p_suggest' => 0.10, 'sort_order' => 1, 'active' => true],
            ['id' => 'dom_b2b', 'topic_id' => 'topic_company', 'name' => 'فروش B2B', 'description' => 'کاتالوگ سازمانی و استعلام', 'p_suggest' => 0.09, 'sort_order' => 2, 'active' => true],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function seedCategories(): array
    {
        return [
            ['id' => 'cat_site', 'name' => 'طراحی سایت', 'description' => 'ساخت یا بازطراحی وب‌سایت', 'sort_order' => 1, 'active' => true],
            ['id' => 'cat_domain', 'name' => 'دامنه', 'description' => 'ثبت و تمدید دامنه', 'sort_order' => 2, 'active' => true],
            ['id' => 'cat_gateway', 'name' => 'درگاه پرداخت', 'description' => 'اتصال درگاه با نمایش کارمزد', 'sort_order' => 3, 'active' => true],
            ['id' => 'cat_tech', 'name' => 'پشتیبانی فنی', 'description' => 'سطح پشتیبانی فنی', 'sort_order' => 4, 'active' => true],
            ['id' => 'cat_content', 'name' => 'پشتیبانی محتوا', 'description' => 'درج محصول، نوشته و برگه', 'sort_order' => 5, 'active' => true],
            ['id' => 'cat_enamad', 'name' => 'اینماد', 'description' => 'نماد اعتماد الکترونیکی', 'sort_order' => 6, 'active' => true],
            ['id' => 'cat_infra', 'name' => 'زیرساخت', 'description' => 'سرور، SSL و آماده‌سازی', 'sort_order' => 7, 'active' => true],
            ['id' => 'cat_marketing', 'name' => 'بازاریابی', 'description' => 'تبلیغات و کانال‌های فروش', 'sort_order' => 8, 'active' => true],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function seedCatalog(): array
    {
        $rows = [
            // طراحی سایت — یک گزینه از گروه
            ['id' => 'svc_site_new', 'name' => 'سایت ندارم — طراحی از صفر', 'billing' => 'once', 'period_months' => 1, 'renewable' => false, 'amount' => 45000000, 'category_id' => 'cat_site', 'category' => 'طراحی سایت', 'choice_group' => 'site_status', 'choice_value' => 'need_new', 'default_selected' => true, 'description' => 'طراحی و پیاده‌سازی فروشگاه یا سایت اختصاصی از صفر'],
            ['id' => 'svc_site_redesign', 'name' => 'سایت دارم — نیاز به بازطراحی', 'billing' => 'once', 'period_months' => 1, 'renewable' => false, 'amount' => 28000000, 'category_id' => 'cat_site', 'category' => 'طراحی سایت', 'choice_group' => 'site_status', 'choice_value' => 'redesign', 'default_selected' => false, 'description' => 'بازطراحی تجربه کاربری و ظاهر روی زیرساخت فعلی'],
            ['id' => 'svc_site_keep', 'name' => 'سایت دارم — بازطراحی لازم نیست', 'billing' => 'once', 'period_months' => 1, 'renewable' => false, 'amount' => 0, 'category_id' => 'cat_site', 'category' => 'طراحی سایت', 'choice_group' => 'site_status', 'choice_value' => 'keep', 'default_selected' => false, 'description' => 'ادامه با سایت فعلی بدون بازطراحی'],
            ['id' => 'svc_site_unsure', 'name' => 'سایت دارم — نمی‌دانم', 'billing' => 'once', 'period_months' => 1, 'renewable' => false, 'amount' => 8000000, 'category_id' => 'cat_site', 'category' => 'طراحی سایت', 'choice_group' => 'site_status', 'choice_value' => 'unsure', 'default_selected' => false, 'description' => 'ممیزی کوتاه و پیشنهاد مسیر (نگهداری یا بازطراحی)'],

            // دامنه
            ['id' => 'svc_dom_ir', 'name' => 'خرید دامنه .ir', 'billing' => 'yearly', 'period_months' => 12, 'renewable' => true, 'amount' => 180000, 'category_id' => 'cat_domain', 'category' => 'دامنه', 'choice_group' => 'domain_tld', 'choice_value' => 'ir', 'default_selected' => true, 'description' => 'ثبت و تمدید سالانه دامنه ملی'],
            ['id' => 'svc_dom_com', 'name' => 'خرید دامنه .com', 'billing' => 'yearly', 'period_months' => 12, 'renewable' => true, 'amount' => 900000, 'category_id' => 'cat_domain', 'category' => 'دامنه', 'choice_group' => 'domain_tld', 'choice_value' => 'com', 'default_selected' => false, 'description' => 'ثبت و تمدید سالانه دامنه بین‌المللی'],
            ['id' => 'svc_dom_both', 'name' => 'هر دو دامنه (.ir و .com)', 'billing' => 'yearly', 'period_months' => 12, 'renewable' => true, 'amount' => 1050000, 'category_id' => 'cat_domain', 'category' => 'دامنه', 'choice_group' => 'domain_tld', 'choice_value' => 'both', 'default_selected' => false, 'description' => 'ثبت همزمان دامنه ملی و بین‌المللی'],
            ['id' => 'svc_dom_none', 'name' => 'دامنه دارم / نیاز ندارم', 'billing' => 'once', 'period_months' => 1, 'renewable' => false, 'amount' => 0, 'category_id' => 'cat_domain', 'category' => 'دامنه', 'choice_group' => 'domain_tld', 'choice_value' => 'none', 'default_selected' => false, 'description' => 'بدون خرید دامنه جدید'],

            // درگاه — چندتایی مجاز + کارمزد برای نمایش
            ['id' => 'svc_gw_zarinpal', 'name' => 'زرین‌پال', 'billing' => 'once', 'period_months' => 1, 'renewable' => false, 'amount' => 0, 'category_id' => 'cat_gateway', 'category' => 'درگاه پرداخت', 'fee_label' => 'کارمزد حدود ۱٪ + مالیات', 'default_selected' => true, 'description' => 'اتصال درگاه زرین‌پال'],
            ['id' => 'svc_gw_idpay', 'name' => 'آی‌دی‌پی', 'billing' => 'once', 'period_months' => 1, 'renewable' => false, 'amount' => 0, 'category_id' => 'cat_gateway', 'category' => 'درگاه پرداخت', 'fee_label' => 'کارمزد حدود ۱٪', 'default_selected' => false, 'description' => 'اتصال درگاه آی‌دی‌پی'],
            ['id' => 'svc_gw_behpardakht', 'name' => 'به‌پرداخت ملت', 'billing' => 'once', 'period_months' => 1, 'renewable' => false, 'amount' => 2500000, 'category_id' => 'cat_gateway', 'category' => 'درگاه پرداخت', 'fee_label' => 'کارمزد بانکی طبق تعرفه بانک', 'default_selected' => false, 'description' => 'راه‌اندازی درگاه مستقیم بانکی'],
            ['id' => 'svc_gw_nextpay', 'name' => 'نکست‌پی', 'billing' => 'once', 'period_months' => 1, 'renewable' => false, 'amount' => 0, 'category_id' => 'cat_gateway', 'category' => 'درگاه پرداخت', 'fee_label' => 'کارمزد حدود ۱٪', 'default_selected' => false, 'description' => 'اتصال درگاه نکست‌پی'],

            // پشتیبانی فنی — یک سطح
            ['id' => 'svc_tech_basic', 'name' => 'پشتیبانی فنی پایه', 'billing' => 'monthly', 'period_months' => 1, 'renewable' => true, 'amount' => 2500000, 'category_id' => 'cat_tech', 'category' => 'پشتیبانی فنی', 'choice_group' => 'tech_support', 'choice_value' => 'basic', 'default_selected' => true, 'description' => 'رفع اشکال، به‌روزرسانی امنیتی و پاسخ در ساعات اداری'],
            ['id' => 'svc_tech_pro', 'name' => 'پشتیبانی فنی حرفه‌ای', 'billing' => 'monthly', 'period_months' => 1, 'renewable' => true, 'amount' => 5500000, 'category_id' => 'cat_tech', 'category' => 'پشتیبانی فنی', 'choice_group' => 'tech_support', 'choice_value' => 'pro', 'default_selected' => false, 'description' => 'مانیتورینگ، اولویت پاسخ و تغییرات کوچک ماهانه'],
            ['id' => 'svc_tech_24', 'name' => 'پشتیبانی فنی ۲۴/۷', 'billing' => 'monthly', 'period_months' => 1, 'renewable' => true, 'amount' => 9500000, 'category_id' => 'cat_tech', 'category' => 'پشتیبانی فنی', 'choice_group' => 'tech_support', 'choice_value' => '247', 'default_selected' => false, 'description' => 'پوشش شبانه‌روزی برای فروشگاه‌های پرترافیک'],
            ['id' => 'svc_tech_none', 'name' => 'بدون پشتیبانی فنی قراردادی', 'billing' => 'once', 'period_months' => 1, 'renewable' => false, 'amount' => 0, 'category_id' => 'cat_tech', 'category' => 'پشتیبانی فنی', 'choice_group' => 'tech_support', 'choice_value' => 'none', 'default_selected' => false, 'description' => 'فقط تحویل پروژه بدون بسته پشتیبانی'],

            // محتوا — چندتایی
            ['id' => 'svc_content_products_50', 'name' => 'درج ۵۰ محصول', 'billing' => 'once', 'period_months' => 1, 'renewable' => false, 'amount' => 4500000, 'category_id' => 'cat_content', 'category' => 'پشتیبانی محتوا', 'default_selected' => true, 'description' => 'ورود اولیه تا ۵۰ محصول با تصویر و مشخصات'],
            ['id' => 'svc_content_products_200', 'name' => 'درج ۲۰۰ محصول', 'billing' => 'once', 'period_months' => 1, 'renewable' => false, 'amount' => 14000000, 'category_id' => 'cat_content', 'category' => 'پشتیبانی محتوا', 'default_selected' => false, 'description' => 'ورود اولیه تا ۲۰۰ محصول'],
            ['id' => 'svc_content_posts_10', 'name' => '۱۰ نوشته وبلاگ', 'billing' => 'once', 'period_months' => 1, 'renewable' => false, 'amount' => 6000000, 'category_id' => 'cat_content', 'category' => 'پشتیبانی محتوا', 'default_selected' => false, 'description' => 'تولید و انتشار ۱۰ مطلب'],
            ['id' => 'svc_content_pages_5', 'name' => '۵ برگه محتوایی', 'billing' => 'once', 'period_months' => 1, 'renewable' => false, 'amount' => 3500000, 'category_id' => 'cat_content', 'category' => 'پشتیبانی محتوا', 'default_selected' => true, 'description' => 'درباره ما، تماس، قوانین و صفحات پایه'],
            ['id' => 'svc_content_monthly', 'name' => 'پشتیبانی محتوای ماهانه', 'billing' => 'monthly', 'period_months' => 1, 'renewable' => true, 'amount' => 4000000, 'category_id' => 'cat_content', 'category' => 'پشتیبانی محتوا', 'default_selected' => false, 'description' => 'به‌روزرسانی منظم محصولات و نوشته‌ها'],

            // اینماد — یک گزینه
            ['id' => 'svc_enamad_none', 'name' => 'اینماد لازم نیست', 'billing' => 'once', 'period_months' => 1, 'renewable' => false, 'amount' => 0, 'category_id' => 'cat_enamad', 'category' => 'اینماد', 'choice_group' => 'enamad', 'choice_value' => 'none', 'default_selected' => false, 'description' => 'بدون پیگیری نماد اعتماد'],
            ['id' => 'svc_enamad_has_license', 'name' => 'اینماد لازم است — پروانه کسب دارم', 'billing' => 'custom', 'period_months' => 24, 'renewable' => true, 'amount' => 3500000, 'category_id' => 'cat_enamad', 'category' => 'اینماد', 'choice_group' => 'enamad', 'choice_value' => 'has_license', 'default_selected' => true, 'description' => 'پیگیری و دریافت اینماد با پروانه موجود'],
            ['id' => 'svc_enamad_no_license', 'name' => 'اینماد لازم است — پروانه ندارم', 'billing' => 'custom', 'period_months' => 24, 'renewable' => true, 'amount' => 9500000, 'category_id' => 'cat_enamad', 'category' => 'اینماد', 'choice_group' => 'enamad', 'choice_value' => 'no_license', 'default_selected' => false, 'description' => 'مشاوره مجوز + پیگیری اینماد (هزینه مجوز جداگانه اعلام می‌شود)'],

            // زیرساخت و بازاریابی
            ['id' => 'svc_hosting', 'name' => 'هاست و سرور مدیریت‌شده', 'billing' => 'yearly', 'period_months' => 12, 'renewable' => true, 'amount' => 8500000, 'category_id' => 'cat_infra', 'category' => 'زیرساخت', 'default_selected' => true, 'description' => 'میزبانی بهینه فروشگاه با پشتیبان‌گیری'],
            ['id' => 'svc_ssl', 'name' => 'گواهی SSL', 'billing' => 'yearly', 'period_months' => 12, 'renewable' => true, 'amount' => 0, 'category_id' => 'cat_infra', 'category' => 'زیرساخت', 'default_selected' => true, 'description' => 'گواهی امنیتی رایگان یا سازمانی'],
            ['id' => 'svc_packaging', 'name' => 'بسته‌بندی و راه‌اندازی اولیه', 'billing' => 'once', 'period_months' => 1, 'renewable' => false, 'amount' => 5000000, 'category_id' => 'cat_infra', 'category' => 'زیرساخت', 'default_selected' => true, 'description' => 'راه‌اندازی محیط، اتصال دامنه و تحویل'],
            ['id' => 'svc_torob', 'name' => 'شارژ و اتصال ترب', 'billing' => 'monthly', 'period_months' => 1, 'renewable' => true, 'amount' => 2000000, 'category_id' => 'cat_marketing', 'category' => 'بازاریابی', 'default_selected' => false, 'description' => 'اتصال کاتالوگ و شارژ ماهانه تبلیغات'],
        ];

        $out = [];
        $i = 0;
        foreach ($rows as $row) {
            ++$i;
            $out[] = [
                'id' => (string) $row['id'],
                'name' => (string) $row['name'],
                'billing' => (string) $row['billing'],
                'period_months' => (int) $row['period_months'],
                'renewable' => ! empty($row['renewable']),
                'amount' => (float) $row['amount'],
                'active' => true,
                'default_selected' => ! empty($row['default_selected']),
                'category' => (string) $row['category'],
                'category_id' => (string) ($row['category_id'] ?? ''),
                'description' => (string) ($row['description'] ?? ''),
                'choice_group' => (string) ($row['choice_group'] ?? ''),
                'choice_value' => (string) ($row['choice_value'] ?? ''),
                'fee_label' => (string) ($row['fee_label'] ?? ''),
                'sort_order' => $i,
            ];
        }

        return $out;
    }

    public static function clampDuration(int $value, ?array $options = null): int
    {
        $opts = $options;
        if (! is_array($opts) || $opts === []) {
            $opts = self::DURATION_OPTIONS;
        }
        $opts = array_values(array_map('intval', $opts));
        if (in_array($value, $opts, true)) {
            return $value;
        }
        $best = $opts[0];
        foreach ($opts as $opt) {
            if (abs($opt - $value) < abs($best - $value)) {
                $best = $opt;
            }
        }

        return (int) $best;
    }

    /**
     * @return array<string, mixed>
     */
    public static function get(): array
    {
        try {
            $row = SalesRahnSetting::query()->first();
            $raw = is_array($row?->payload) ? $row->payload : [];

            return self::mergeDefaults($raw);
        } catch (\Throwable) {
            return self::mergeDefaults([]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function save(array $payload): array
    {
        $current = self::get();
        $next = self::sanitize(array_merge($current, $payload));

        $row = SalesRahnSetting::query()->first();
        if ($row) {
            $row->update(['payload' => $next]);
        } else {
            SalesRahnSetting::query()->create(['payload' => $next]);
        }

        return $next;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private static function mergeDefaults(array $raw): array
    {
        $defaults = self::defaults();
        $out = array_merge($defaults, $raw);

        foreach (['topics', 'domains', 'categories'] as $key) {
            if (empty($out[$key]) || ! is_array($out[$key])) {
                $out[$key] = $defaults[$key];
            }
        }

        if (empty($out['catalog']) || ! is_array($out['catalog'])) {
            $out['catalog'] = $defaults['catalog'];
        } else {
            // Legacy flat catalog without category_id → merge missing seed services by id.
            $byId = [];
            foreach ($out['catalog'] as $row) {
                if (is_array($row) && ! empty($row['id'])) {
                    $byId[(string) $row['id']] = $row;
                }
            }
            $hasTaxonomy = false;
            foreach ($byId as $row) {
                if (! empty($row['category_id']) || ! empty($row['choice_group'])) {
                    $hasTaxonomy = true;
                    break;
                }
            }
            if (! $hasTaxonomy) {
                foreach ($defaults['catalog'] as $seed) {
                    $id = (string) $seed['id'];
                    if (! isset($byId[$id])) {
                        $byId[$id] = $seed;
                    } else {
                        $byId[$id] = array_merge($seed, $byId[$id], [
                            'category_id' => $byId[$id]['category_id'] ?? $seed['category_id'],
                            'choice_group' => $byId[$id]['choice_group'] ?? $seed['choice_group'],
                            'fee_label' => $byId[$id]['fee_label'] ?? $seed['fee_label'],
                        ]);
                    }
                }
                $out['catalog'] = array_values($byId);
            }
        }

        if (empty($out['sales_definition']) || ! is_array($out['sales_definition'])) {
            $out['sales_definition'] = $defaults['sales_definition'];
        } else {
            $out['sales_definition'] = array_merge($defaults['sales_definition'], $out['sales_definition']);
        }
        if (empty($out['review']) || ! is_array($out['review'])) {
            $out['review'] = $defaults['review'];
        } else {
            $out['review'] = array_merge($defaults['review'], $out['review']);
        }
        if (empty($out['duration_options']) || ! is_array($out['duration_options'])) {
            $out['duration_options'] = $defaults['duration_options'];
        }
        if (empty($out['wizard_steps']) || ! is_array($out['wizard_steps'])) {
            $out['wizard_steps'] = $defaults['wizard_steps'];
        }

        return self::sanitize($out);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function sanitize(array $data): array
    {
        $defaults = self::defaults();

        $catalog = [];
        if (! empty($data['catalog']) && is_array($data['catalog'])) {
            $order = 0;
            foreach ($data['catalog'] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                ++$order;
                $id = preg_replace('/[^a-z0-9_\-]/i', '', (string) ($row['id'] ?? '')) ?: ('svc_'.bin2hex(random_bytes(4)));
                $billing = strtolower((string) ($row['billing'] ?? 'once'));
                if (! in_array($billing, ['once', 'monthly', 'yearly', 'custom'], true)) {
                    $billing = 'once';
                }
                $catalog[] = [
                    'id' => $id,
                    'name' => trim((string) ($row['name'] ?? '')),
                    'billing' => $billing,
                    'period_months' => max(1, (int) ($row['period_months'] ?? 1)),
                    'renewable' => ! empty($row['renewable']),
                    'amount' => max(0.0, (float) ($row['amount'] ?? 0)),
                    'active' => ! isset($row['active']) || ! empty($row['active']),
                    'default_selected' => ! empty($row['default_selected']),
                    'category' => trim((string) ($row['category'] ?? '')),
                    'category_id' => preg_replace('/[^a-z0-9_\-]/i', '', (string) ($row['category_id'] ?? '')) ?: '',
                    'description' => trim((string) ($row['description'] ?? '')),
                    'choice_group' => preg_replace('/[^a-z0-9_\-]/i', '', (string) ($row['choice_group'] ?? '')) ?: '',
                    'choice_value' => preg_replace('/[^a-z0-9_\-]/i', '', (string) ($row['choice_value'] ?? '')) ?: '',
                    'fee_label' => trim((string) ($row['fee_label'] ?? '')),
                    'show_when_item_id' => preg_replace('/[^a-z0-9_\-]/i', '', (string) ($row['show_when_item_id'] ?? '')) ?: '',
                    'sort_order' => isset($row['sort_order']) ? (int) $row['sort_order'] : $order,
                ];
            }
            usort($catalog, static fn ($a, $b) => (int) $a['sort_order'] <=> (int) $b['sort_order']);
        }

        $topics = self::sanitizeNamedList($data['topics'] ?? null, $defaults['topics'], false);
        $domains = self::sanitizeDomains($data['domains'] ?? null, $defaults['domains']);
        $categories = self::sanitizeCategories($data['categories'] ?? null, $defaults['categories']);
        $wizardSteps = self::sanitizeWizardSteps($data['wizard_steps'] ?? null, $defaults['wizard_steps']);

        $salesDef = $defaults['sales_definition'];
        if (! empty($data['sales_definition']) && is_array($data['sales_definition'])) {
            foreach (['G', 'R', 'D', 'X'] as $key) {
                if (empty($data['sales_definition'][$key]) || ! is_array($data['sales_definition'][$key])) {
                    continue;
                }
                $salesDef[$key] = [
                    'enabled' => ! empty($data['sales_definition'][$key]['enabled']),
                    'label' => trim((string) ($data['sales_definition'][$key]['label'] ?? $salesDef[$key]['label'])),
                ];
            }
        }

        $review = $defaults['review'];
        if (! empty($data['review']) && is_array($data['review'])) {
            $review = [
                'enabled' => ! empty($data['review']['enabled']),
                'deviation_percent' => max(0.0, (float) ($data['review']['deviation_percent'] ?? 25)),
                'consecutive_months' => max(1, (int) ($data['review']['consecutive_months'] ?? 3)),
            ];
        }

        $clause = isset($data['clause_template'])
            ? trim((string) $data['clause_template'])
            : $defaults['clause_template'];
        if ($clause === '') {
            $clause = $defaults['clause_template'];
        }

        $durationOptions = [];
        if (! empty($data['duration_options']) && is_array($data['duration_options'])) {
            foreach ($data['duration_options'] as $opt) {
                $n = (int) $opt;
                if ($n > 0) {
                    $durationOptions[] = $n;
                }
            }
        }
        $durationOptions = array_values(array_unique($durationOptions ?: self::DURATION_OPTIONS));
        sort($durationOptions);

        $pMin = max(0.0, min(1.0, (float) ($data['p_min'] ?? $defaults['p_min'])));
        $pMax = max($pMin, min(1.0, (float) ($data['p_max'] ?? $defaults['p_max'])));
        $pDef = max($pMin, min($pMax, (float) ($data['p_default'] ?? $defaults['p_default'])));
        $T = self::clampDuration((int) ($data['T'] ?? $defaults['T']), $durationOptions);

        return [
            'T' => $T,
            'm' => max(0.0, (float) ($data['m'] ?? $defaults['m'])),
            'k' => max(0.0, min(1.0, (float) ($data['k'] ?? $defaults['k']))),
            'p_min' => $pMin,
            'p_max' => $pMax,
            'p_default' => $pDef,
            's_hat_default' => max(0.0, (float) ($data['s_hat_default'] ?? $defaults['s_hat_default'])),
            'duration_options' => $durationOptions,
            'clause_template' => $clause,
            'review' => $review,
            'sales_definition' => $salesDef,
            'topics' => $topics,
            'domains' => $domains,
            'categories' => $categories,
            'catalog' => $catalog ?: $defaults['catalog'],
            'wizard_steps' => $wizardSteps,
        ];
    }

    /**
     * @param  mixed  $raw
     * @param  list<array<string, mixed>>  $fallback
     * @return list<array<string, mixed>>
     */
    private static function sanitizeNamedList(mixed $raw, array $fallback, bool $withTopic): array
    {
        if (! is_array($raw) || $raw === []) {
            return $fallback;
        }
        $out = [];
        $order = 0;
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            ++$order;
            $id = preg_replace('/[^a-z0-9_\-]/i', '', (string) ($row['id'] ?? '')) ?: ('item_'.bin2hex(random_bytes(3)));
            $item = [
                'id' => $id,
                'name' => trim((string) ($row['name'] ?? '')),
                'description' => trim((string) ($row['description'] ?? '')),
                'sort_order' => isset($row['sort_order']) ? (int) $row['sort_order'] : $order,
                'active' => ! isset($row['active']) || ! empty($row['active']),
            ];
            if ($withTopic) {
                $item['topic_id'] = preg_replace('/[^a-z0-9_\-]/i', '', (string) ($row['topic_id'] ?? '')) ?: '';
                $item['p_suggest'] = max(0.0, min(1.0, (float) ($row['p_suggest'] ?? 0.1)));
            }
            $out[] = $item;
        }
        usort($out, static fn ($a, $b) => (int) $a['sort_order'] <=> (int) $b['sort_order']);

        return $out ?: $fallback;
    }

    /**
     * @param  mixed  $raw
     * @param  list<array<string, mixed>>  $fallback
     * @return list<array<string, mixed>>
     */
    private static function sanitizeDomains(mixed $raw, array $fallback): array
    {
        return self::sanitizeNamedList($raw, $fallback, true);
    }

    /**
     * @param  mixed  $raw
     * @param  list<array<string, mixed>>  $fallback
     * @return list<array<string, mixed>>
     */
    private static function sanitizeCategories(mixed $raw, array $fallback): array
    {
        $list = self::sanitizeNamedList($raw, $fallback, false);
        if (! is_array($raw) || $raw === []) {
            return $list;
        }
        $byId = [];
        foreach ($raw as $row) {
            if (! is_array($row) || empty($row['id'])) {
                continue;
            }
            $byId[(string) $row['id']] = $row;
        }
        foreach ($list as &$item) {
            $src = $byId[(string) $item['id']] ?? [];
            $item['topic_id'] = preg_replace('/[^a-z0-9_\-]/i', '', (string) ($src['topic_id'] ?? $item['topic_id'] ?? '')) ?: '';
            $item['domain_id'] = preg_replace('/[^a-z0-9_\-]/i', '', (string) ($src['domain_id'] ?? $item['domain_id'] ?? '')) ?: '';
        }
        unset($item);

        return $list;
    }

    /**
     * @param  mixed  $raw
     * @param  list<array<string, mixed>>  $fallback
     * @return list<array<string, mixed>>
     */
    private static function sanitizeWizardSteps(mixed $raw, array $fallback): array
    {
        $allowedTypes = ['business', 'topic', 'domain', 'services', 'deal', 'summary', 'note'];
        if (! is_array($raw) || $raw === []) {
            return $fallback;
        }
        $out = [];
        $order = 0;
        $seenSystem = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            ++$order;
            $type = strtolower((string) ($row['type'] ?? 'note'));
            if (! in_array($type, $allowedTypes, true)) {
                $type = 'note';
            }
            $id = preg_replace('/[^a-z0-9_\-]/i', '', (string) ($row['id'] ?? '')) ?: ('step_'.bin2hex(random_bytes(3)));
            $system = ! empty($row['system']) || in_array($type, ['business', 'topic', 'domain', 'services', 'deal', 'summary'], true);
            if ($system && $type !== 'note') {
                $id = $type;
                if (isset($seenSystem[$type])) {
                    continue;
                }
                $seenSystem[$type] = true;
            }
            $out[] = [
                'id' => $id,
                'type' => $type,
                'label' => trim((string) ($row['label'] ?? $type)) ?: $type,
                'body' => trim((string) ($row['body'] ?? '')),
                'sort_order' => isset($row['sort_order']) ? (int) $row['sort_order'] : $order,
                'active' => ! isset($row['active']) || ! empty($row['active']),
                'system' => $system && $type !== 'note',
            ];
        }
        // Ensure all system steps exist.
        foreach ($fallback as $seed) {
            $type = (string) $seed['type'];
            if (! isset($seenSystem[$type])) {
                $out[] = $seed;
                $seenSystem[$type] = true;
            }
        }
        usort($out, static fn ($a, $b) => (int) $a['sort_order'] <=> (int) $b['sort_order']);

        return $out ?: $fallback;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function catalogMap(): array
    {
        $settings = self::get();
        $map = [];
        foreach ((array) $settings['catalog'] as $row) {
            if (empty($row['active'])) {
                continue;
            }
            $map[(string) $row['id']] = $row;
        }

        return $map;
    }

    /**
     * @param  array<int, string>  $selectedIds
     * @param  array<int, array<string, mixed>>|null  $override
     * @return array<int, array<string, mixed>>
     */
    public static function resolveItems(array $selectedIds, ?array $override = null): array
    {
        $map = [];
        if (is_array($override)) {
            foreach ($override as $row) {
                if (is_array($row) && ! empty($row['id'])) {
                    $map[(string) $row['id']] = $row;
                }
            }
        } else {
            $map = self::catalogMap();
            foreach ((array) self::get()['catalog'] as $row) {
                $map[(string) $row['id']] = $row;
            }
        }

        $items = [];
        foreach ($selectedIds as $id) {
            $id = (string) $id;
            if (isset($map[$id])) {
                $items[] = $map[$id];
            }
        }

        return $items;
    }
}
