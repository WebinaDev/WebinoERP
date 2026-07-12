<?php

namespace Modules\Integrations\Support\BaleDefaults;

/**
 * آرایه‌های ساخت‌یافته: پشتیبانی، کاتالوگ، FAQ و مستندات امکانات.
 */
final class StructuredDefaults
{
    /**
     * @return array<string, mixed>
     */
    public static function get(): array
    {
        return [
            'support_items' => self::supportItems(),
            'feature_docs' => [],
            'catalog_items' => self::catalogItems(),
            'faq_items' => self::faqItems(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function supportItems(): array
    {
        return [
            [
                'emoji' => '🟣',
                'title' => 'پشتیبانی بله',
                'description' => 'ارتباط مستقیم در بله',
                'action_type' => 'username',
                'action_value' => '@webina_support',
                'sort' => 10,
            ],
            [
                'emoji' => '📞',
                'title' => 'تلفن پشتیبانی',
                'description' => 'پاسخگویی در ساعات اداری',
                'action_type' => 'phone',
                'action_value' => '02100000000',
                'sort' => 20,
            ],
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    private static function catalogItems(): array
    {
        return [
            ['key' => 'store_navigation', 'title' => 'فروشگاه در چت', 'description' => 'مرور دسته ها، جستجو، نمایش محصول و انتخاب تنوع'],
            ['key' => 'cart_checkout', 'title' => 'سبد و تسویه', 'description' => 'افزودن/حذف محصول، کد تخفیف، ثبت سفارش'],
            ['key' => 'order_updates', 'title' => 'پیگیری سفارش', 'description' => 'نمایش وضعیت سفارش و اعلان تغییر وضعیت'],
            ['key' => 'marketing', 'title' => 'بازاریابی', 'description' => 'پیام گروهی، قالب پیام، شخصی سازی متون'],
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    private static function faqItems(): array
    {
        return [
            ['question' => 'این ربات روی چه پلتفرمی نصب می شود؟', 'answer' => 'روی Laravel/Next و طبق استاندارد رسمی Webino.'],
            ['question' => 'پرداخت چطور انجام می شود؟', 'answer' => 'از طریق درگاه‌های متصل به فروشگاه Webino پشتیبانی می شود.'],
        ];
    }
}
