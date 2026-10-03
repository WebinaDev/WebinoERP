<?php

namespace Modules\SiteBuilder\Support;

use Modules\Core\Entities\CoreHostingSetting;

/**
 * Bearer token the tenant dashboard expects as WEBINO_ERP_API_TOKEN.
 * Env wins when set. Otherwise the hosting row stores one shared secret
 * that provisioning writes into each site .env.
 */
final class ErpApiToken
{
    public static function current(): string
    {
        $fromEnv = trim((string) env('WEBINO_ERP_API_TOKEN', ''));
        if ($fromEnv !== '') {
            return $fromEnv;
        }

        $settings = CoreHostingSetting::current();
        $stored = trim((string) ($settings->erp_api_token ?? ''));
        if ($stored !== '') {
            return $stored;
        }

        $stored = bin2hex(random_bytes(32));
        $settings->erp_api_token = $stored;
        $settings->save();

        return $stored;
    }
}
