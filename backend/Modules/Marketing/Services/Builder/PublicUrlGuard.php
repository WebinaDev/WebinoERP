<?php

namespace Modules\Marketing\Services\Builder;

/** Outbound URL guard for builder webhooks: https only, no private / loopback / link-local / metadata hosts. */
class PublicUrlGuard
{
    /** @return array{scheme: string, host: string} */
    public static function parseHttp(string $url): array
    {
        $parts = parse_url(trim($url));
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            throw new \InvalidArgumentException('invalid url');
        }

        return ['scheme' => strtolower((string) $parts['scheme']), 'host' => strtolower(trim((string) $parts['host'], '[]'))];
    }

    public static function isBlockedHost(string $host): bool
    {
        $host = strtolower($host);
        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local') || str_ends_with($host, '.internal')
            || $host === 'metadata.google.internal') {
            return true;
        }

        return filter_var($host, FILTER_VALIDATE_IP) ? self::isBlockedIp($host) : false;
    }

    public static function isBlockedIp(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return true;
        }

        return ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
            || str_starts_with($ip, '169.254.') || str_starts_with($ip, '100.64.') || $ip === '0.0.0.0';
    }
}
