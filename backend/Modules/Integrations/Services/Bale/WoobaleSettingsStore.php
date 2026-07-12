<?php

namespace Modules\Integrations\Services\Bale;

use Modules\Integrations\Entities\IntegrationSetting;

/**
 * تنظیمات سازگار با Woobale (وبهوک، سلامت، توکن ربات).
 */
class WoobaleSettingsStore
{
    private const INTEGRATION = 'woobale';

    private const KEY_SETTINGS = 'settings';

    private const KEY_HEALTH = 'internal_health_token';

    /**
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        $defaults = [
            'bot_token' => '',
            'webhook_secret' => '',
            'webhook_require_secret' => '1',
        ];
        $stored = IntegrationSetting::getJson(self::INTEGRATION, self::KEY_SETTINGS, []);

        return array_merge($defaults, is_array($stored) ? $stored : []);
    }

    public function webhookSecret(): string
    {
        $s = $this->settings();

        return trim((string) ($s['webhook_secret'] ?? ''));
    }

    public function requireWebhookSecret(): bool
    {
        $s = $this->settings();
        if (! isset($s['webhook_require_secret'])) {
            return true;
        }

        return (string) $s['webhook_require_secret'] !== '0';
    }

    /**
     * توکن GET /woobale/v1/health — در صورت خالی بودن تولید می‌شود.
     */
    public function healthCheckToken(): string
    {
        $t = IntegrationSetting::getString(self::INTEGRATION, self::KEY_HEALTH, '');
        if ($t !== '') {
            return $t;
        }
        $t = bin2hex(random_bytes(16));
        IntegrationSetting::putString(self::INTEGRATION, self::KEY_HEALTH, $t);

        return $t;
    }
}
