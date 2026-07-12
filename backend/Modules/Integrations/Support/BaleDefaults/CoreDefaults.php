<?php

namespace Modules\Integrations\Support\BaleDefaults;

/**
 * فلگ‌ها، توکن‌ها و کلیدهای پیکربندی اصلی (هم‌تراز wbb_settings در وردپرس).
 */
final class CoreDefaults
{
    /**
     * @return array<string, mixed>
     */
    public static function get(): array
    {
        return [
            'bot_token' => '',
            'provider_token' => '',
            'channel_id' => '',
            'channel_join_url' => '',
            'membership_required' => '1',
            'webhook_mode' => 'rest',
            'enable_menu_features' => '1',
            'enable_menu_support' => '1',
            'enable_menu_profile' => '1',
            'enable_menu_businesses' => '1',
            'enable_menu_pricing' => '1',
            'enable_menu_faq' => '1',
            'enable_menu_why_bale' => '1',
            'enable_auto_register_user' => '1',
            'enable_auto_lead_from_business' => '1',
            'sales_wc_product_id' => 0,
            'currency_unit' => 'rial',
            'plan_product_map' => [],
            'webhook_secret' => '',
        ];
    }
}
