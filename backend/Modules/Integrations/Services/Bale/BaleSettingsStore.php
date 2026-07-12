<?php

namespace Modules\Integrations\Services\Bale;

use Modules\Integrations\Entities\IntegrationSetting;
use Modules\Integrations\Support\BaleSettingsDefaults;

/**
 * خواندن/نوشتن تنظیمات ربات بله در integration_settings (کلید settings، JSON).
 */
class BaleSettingsStore
{
    private const INTEGRATION = 'bale';

    private const KEY = 'settings';

    /**
     * @return array<string, mixed>
     */
    public function merged(): array
    {
        $stored = IntegrationSetting::getJson(self::INTEGRATION, self::KEY, []);

        return array_merge(BaleSettingsDefaults::all(), is_array($stored) ? $stored : []);
    }

    /**
     * پاسخ GET: بدون webhook_secret
     *
     * @return array<string, mixed>
     */
    public function forPublicApi(): array
    {
        $s = $this->merged();
        unset($s['webhook_secret']);

        return $s;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed> تنظیمات کامل پس از ذخیره (شامل webhook_secret)
     */
    public function mergeAndSave(array $body): array
    {
        $merged = $this->sanitize(array_merge($this->merged(), $body));
        IntegrationSetting::putJson(self::INTEGRATION, self::KEY, $merged);

        return $this->merged();
    }

    public function botToken(): string
    {
        $s = $this->merged();

        return (string) ($s['bot_token'] ?? '');
    }

    public function webhookSecret(): string
    {
        $s = $this->merged();

        return trim((string) ($s['webhook_secret'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $out
     * @return array<string, mixed>
     */
    private function sanitize(array $out): array
    {
        $base = BaleSettingsDefaults::all();
        $out = array_merge($base, $out);

        $out['sales_wc_product_id'] = max(0, (int) ($out['sales_wc_product_id'] ?? 0));

        foreach ([
            'channel_id', 'channel_join_url', 'membership_required', 'webhook_mode',
            'enable_menu_features', 'enable_menu_support', 'enable_menu_profile',
            'enable_menu_businesses', 'enable_menu_pricing', 'enable_menu_faq',
            'enable_menu_why_bale', 'enable_auto_register_user', 'enable_auto_lead_from_business',
            'currency_unit', 'bot_token', 'provider_token', 'webhook_secret',
        ] as $k) {
            if (isset($out[$k]) && is_bool($out[$k])) {
                $out[$k] = $out[$k] ? '1' : '0';
            }
        }

        foreach (['support_items', 'feature_docs', 'catalog_items', 'faq_items', 'plan_product_map'] as $arrKey) {
            if (isset($out[$arrKey]) && ! is_array($out[$arrKey])) {
                unset($out[$arrKey]);
            }
        }

        return $out;
    }
}
