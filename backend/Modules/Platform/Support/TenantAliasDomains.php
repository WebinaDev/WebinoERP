<?php

namespace Modules\Platform\Support;

use Modules\SiteBuilder\Entities\WebinoSiteProvision;

/**
 * Derive LinkHub (and other) alias hostnames for a tenant primary domain.
 */
final class TenantAliasDomains
{
    public const DEFAULT_LABEL = 'contact';

    public static function normalizeLabel(?string $label): string
    {
        $label = strtolower(trim((string) $label));
        $label = preg_replace('/[^a-z0-9-]/', '', $label) ?: self::DEFAULT_LABEL;
        $label = trim($label, '-');
        if ($label === '' || strlen($label) > 63) {
            return self::DEFAULT_LABEL;
        }

        return $label;
    }

    public static function normalizeHost(?string $host): string
    {
        $host = strtolower(trim((string) $host));
        $host = preg_replace('#^https?://#', '', $host) ?? $host;
        $host = explode('/', $host)[0] ?? $host;
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;

        return preg_replace('/^www\./', '', $host) ?? $host;
    }

    /**
     * Full alias hostname: {label}.{primary} (www stripped from primary).
     */
    public static function hostname(string $primaryDomain, ?string $aliasLabel = null): ?string
    {
        $primary = self::normalizeHost($primaryDomain);
        if ($primary === '' || ! str_contains($primary, '.')) {
            return null;
        }
        $label = self::normalizeLabel($aliasLabel);

        return $label.'.'.$primary;
    }

    /**
     * @return list<string>
     */
    public static function forProvision(WebinoSiteProvision $provision): array
    {
        $label = $provision->alias_subdomain
            ?? ($provision->wizard_payload['alias_subdomain'] ?? null)
            ?? self::DEFAULT_LABEL;
        $host = self::hostname((string) $provision->domain, is_string($label) ? $label : null);
        $hosts = $host ? [$host] : [];

        // Previous primaries kept after domain change so old URLs / SSL hosts keep routing.
        $legacy = $provision->wizard_payload['legacy_domains'] ?? [];
        if (is_array($legacy)) {
            foreach ($legacy as $item) {
                $a = self::normalizeHost(is_string($item) ? $item : '');
                if ($a !== '' && str_contains($a, '.') && ! in_array($a, $hosts, true)) {
                    $hosts[] = $a;
                }
            }
        }

        $primary = self::normalizeHost((string) $provision->domain);

        return array_values(array_filter($hosts, fn (string $h) => $h !== $primary));
    }

    /**
     * Remember a previous primary (+ its contact alias) when the public domain changes.
     *
     * @param  list<string>  $existing
     * @return list<string>
     */
    public static function pushLegacyDomain(array $existing, string $previousPrimary, ?string $aliasLabel = null): array
    {
        $hosts = [];
        foreach ($existing as $item) {
            $a = self::normalizeHost(is_string($item) ? $item : '');
            if ($a !== '' && str_contains($a, '.')) {
                $hosts[] = $a;
            }
        }
        $prev = self::normalizeHost($previousPrimary);
        if ($prev !== '' && str_contains($prev, '.')) {
            $hosts[] = $prev;
            $contact = self::hostname($prev, $aliasLabel);
            if ($contact) {
                $hosts[] = $contact;
            }
        }

        return array_values(array_unique($hosts));
    }

    /**
     * Hostnames for a Caddy site block (primary first, then aliases; www variants omitted — Caddy ACME per listed host).
     *
     * @param  list<string>  $aliases
     * @return list<string>
     */
    public static function siteHosts(string $primaryDomain, array $aliases = []): array
    {
        $primary = self::normalizeHost($primaryDomain);
        $hosts = [];
        if ($primary !== '' && str_contains($primary, '.')) {
            $hosts[] = $primary;
        }
        foreach ($aliases as $alias) {
            $a = self::normalizeHost(is_string($alias) ? $alias : '');
            if ($a !== '' && str_contains($a, '.') && ! in_array($a, $hosts, true)) {
                $hosts[] = $a;
            }
        }

        return $hosts;
    }
}
