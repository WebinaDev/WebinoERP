<?php

namespace Modules\Core\Support;

use App\Models\User;
use Modules\Core\Database\Seeders\RolesAndPermissionsSeeder;
use Modules\Core\Entities\SystemSetting;
use Modules\Integrations\Entities\IntegrationSetting;

/**
 * Admin 2FA is opt-in. A missing phone or SMS provider must not lock the dashboard.
 */
final class AdminTwoFactor
{
    public static function isEnabled(): bool
    {
        try {
            return self::truthy(SystemSetting::get('auth_2fa_required', '0'));
        } catch (\Throwable) {
            return false;
        }
    }

    public static function requiredFor(?User $user): bool
    {
        if (! $user || ! self::isEnabled() || ! self::isSystemManager($user)) {
            return false;
        }

        return self::canDeliverTo($user);
    }

    public static function isSystemManager(mixed $user): bool
    {
        try {
            if (method_exists($user, 'hasRole') && $user->hasRole(RolesAndPermissionsSeeder::ROLE_SYSTEM_MANAGER)) {
                return true;
            }
        } catch (\Throwable) {
            // Role catalog may be missing after a partial seed.
        }

        return false;
    }

    public static function canDeliverTo(User $user): bool
    {
        $phone = trim((string) ($user->phone ?? ''));
        if ($phone === '') {
            return false;
        }

        try {
            $sms = IntegrationSetting::getJson('sms', 'settings', []);
            $provider = (string) ($sms['provider'] ?? config('integrations.sms.default', 'log'));
        } catch (\Throwable) {
            $provider = (string) config('integrations.sms.default', 'log');
        }

        return ! in_array($provider, ['', 'disabled', 'stub', 'log'], true);
    }

    private static function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }
}
