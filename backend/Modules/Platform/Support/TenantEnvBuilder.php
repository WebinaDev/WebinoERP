<?php

namespace Modules\Platform\Support;

use Modules\Core\Entities\CoreHostingSetting;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;
use RuntimeException;

/**
 * Shared tenant .env for local and remote WebinoDashboard provisioners.
 */
final class TenantEnvBuilder
{
    /**
     * @param  array<string, string>  $previous  Existing env keys to preserve (APP_KEY, DB_PASSWORD).
     */
    public static function build(
        WebinoSiteProvision $provision,
        string $siteType,
        string $token,
        array $previous = [],
    ): string {
        $settings = CoreHostingSetting::current();
        $crm = self::resolvePublicCrmUrl($settings);
        $domain = strtolower(trim((string) $provision->domain));
        $seed = json_encode([
            'tenant_name' => $provision->wizard_payload['site_name'] ?? $provision->slug,
            'domain' => $provision->domain,
            'license_key' => $provision->license?->license_key,
            'site_type_slug' => $siteType,
            'business_type_slug' => $siteType,
            'crm_account_id' => $provision->crm_account_id,
            'admin_email' => $provision->wizard_payload['admin_email'] ?? null,
            'admin_name' => $provision->wizard_payload['admin_name'] ?? 'Admin',
            'provision_token' => $token,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $appKey = $previous['APP_KEY'] ?? ('base64:'.base64_encode(random_bytes(32)));
        $dbPassword = $previous['DB_PASSWORD'] ?? bin2hex(random_bytes(12));
        $licenseHmac = (string) (config('app.webinocrm_license_hmac_secret') ?? '');
        $provisionSecret = (string) ($settings->provision_webhook_secret ?? '');
        $origin = 'https://'.$domain;

        return implode("\n", [
            self::line('APP_ENV', 'production'),
            self::line('APP_DEBUG', 'false'),
            self::line('APP_URL', $origin),
            self::line('FRONTEND_URL', $origin),
            self::line('APP_KEY', $appKey),
            self::line('DB_CONNECTION', 'pgsql'),
            self::line('DB_HOST', 'db'),
            self::line('DB_PORT', '5432'),
            self::line('DB_DATABASE', 'webino'),
            self::line('DB_USERNAME', 'webino'),
            self::line('DB_PASSWORD', $dbPassword),
            self::line('REDIS_HOST', 'redis'),
            self::line('REDIS_PORT', '6379'),
            self::line('CACHE_STORE', 'redis'),
            self::line('SESSION_DRIVER', 'file'),
            self::line('QUEUE_CONNECTION', 'redis'),
            self::line('SESSION_SECURE_COOKIE', 'true'),
            self::line('TRUSTED_PROXIES', '*'),
            self::line('SANCTUM_STATEFUL_DOMAINS', $domain.',www.'.$domain),
            self::line('CORS_ALLOWED_ORIGINS', $origin.',https://www.'.$domain),
            self::line('AUTH_COOKIE_NAME', 'webino_auth_token'),
            self::line('LOG_CHANNEL', 'stderr'),
            self::line('LOG_STACK', 'stderr'),
            self::line('RUN_MIGRATIONS', '1'),
            self::line('WEBINO_BASE_URL', $crm),
            self::line('WEBINOCRM_LICENSE_HMAC_SECRET', $licenseHmac),
            self::line('TENANT_LICENSE_KEY', (string) ($provision->license?->license_key ?? '')),
            self::line('TENANT_PROVISION_TOKEN', $token),
            self::line('TENANT_SEED_JSON', (string) $seed),
            self::line('WEBINO_PROVISION_HMAC_SECRET', $provisionSecret),
        ])."\n";
    }

    public static function resolvePublicCrmUrl(?CoreHostingSetting $settings = null): string
    {
        $settings ??= CoreHostingSetting::current();
        $crm = rtrim((string) ($settings->public_crm_url ?: ''), '/');
        if ($crm === '') {
            $crm = rtrim((string) config('app.url'), '/');
        }

        $host = strtolower((string) (parse_url($crm, PHP_URL_HOST) ?: ''));
        if ($crm === '' || in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            throw new RuntimeException(
                'public_crm_url must be a public ERP URL reachable from tenant containers (not localhost).'
            );
        }

        return $crm;
    }

    protected static function line(string $key, string $value): string
    {
        $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $value);

        return $key.'="'.$escaped.'"';
    }
}
