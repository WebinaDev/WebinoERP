<?php

namespace Modules\Core\Services;

use Illuminate\Support\Facades\Schema;
use Modules\Core\Entities\CoreLicense;

/**
 * Domain (+ product) is the license identity. license_key is never required for entitlement.
 */
final class CoreLicenseResolver
{
    /**
     * Apex hosts treated as one license family (subdomain ↔ sibling under any apex).
     * Example: bluecafe.webinaagency.ir ≡ bluecafe.webina.dev
     *
     * @var list<string>
     */
    public const DOMAIN_FAMILIES = [
        'webina.dev',
        'webinaagency.ir',
    ];

    public static function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain) ?? $domain;
        $domain = preg_replace('#/.*$#', '', $domain) ?? $domain;
        $domain = preg_replace('/^www\./', '', $domain) ?? $domain;

        return rtrim($domain, '.');
    }

    public static function normalizeProduct(?string $product): string
    {
        $product = strtolower(trim((string) $product));
        if ($product === '' || $product === 'default') {
            return 'webino';
        }

        // Map common aliases from legacy WP / Dashboard clients.
        return match ($product) {
            'webinodashboard', 'webino-dashboard', 'dashboard' => 'webinodashboard',
            'webinocrm', 'webino-crm', 'crm', 'erp', 'webinoerp', 'webino-erp' => 'webino',
            'wordpress', 'wp', 'webinowp' => 'wordpress',
            default => $product,
        };
    }

    /**
     * Related hostnames under the Webina apex family (self first).
     *
     * @return list<string>
     */
    public static function relatedDomains(string $domain): array
    {
        $domain = self::normalizeDomain($domain);
        if ($domain === '') {
            return [];
        }

        $out = [$domain];
        $apex = null;
        $sub = null;

        foreach (self::DOMAIN_FAMILIES as $candidate) {
            if ($domain === $candidate) {
                $apex = $candidate;
                $sub = '';
                break;
            }
            if (str_ends_with($domain, '.'.$candidate)) {
                $apex = $candidate;
                $sub = substr($domain, 0, -(strlen($candidate) + 1));
                break;
            }
        }

        if ($apex === null) {
            return $out;
        }

        foreach (self::DOMAIN_FAMILIES as $other) {
            if ($other === $apex) {
                continue;
            }
            $candidate = $sub === '' ? $other : $sub.'.'.$other;
            $out[] = $candidate;
        }

        return array_values(array_unique($out));
    }

    /**
     * Resolve entitlement row by domain (+ optional product).
     * Also matches sibling hosts under DOMAIN_FAMILIES (webina.dev ↔ webinaagency.ir).
     * Never filters by license_key. If product-specific row missing, falls back to product=webino then any domain row.
     */
    public static function find(string $domain, ?string $product = null): ?CoreLicense
    {
        $domain = self::normalizeDomain($domain);
        if ($domain === '') {
            return null;
        }

        try {
            if (! Schema::hasTable('core_licenses')) {
                return null;
            }
        } catch (\Throwable) {
            // DB down / pre-migrate — treat as no license rather than 500 the SPA/bootstrap.
            return null;
        }

        $product = self::normalizeProduct($product);
        $candidates = self::relatedDomains($domain);
        $hasProduct = Schema::hasColumn('core_licenses', 'product');

        if ($hasProduct) {
            foreach ($candidates as $host) {
                $exact = CoreLicense::query()
                    ->where('domain', $host)
                    ->where('product', $product)
                    ->orderByDesc('id')
                    ->first();
                if ($exact) {
                    return $exact;
                }
            }

            if ($product !== 'webino') {
                foreach ($candidates as $host) {
                    $fallback = CoreLicense::query()
                        ->where('domain', $host)
                        ->where('product', 'webino')
                        ->orderByDesc('id')
                        ->first();
                    if ($fallback) {
                        return $fallback;
                    }
                }
            }
        }

        // Pre-migration rows (no product column) or legacy single-domain licenses (any product).
        foreach ($candidates as $host) {
            $any = CoreLicense::query()->where('domain', $host)->orderByDesc('id')->first();
            if ($any) {
                return $any;
            }
        }

        return null;
    }

    /**
     * Internal placeholder for deprecated license_key column (unique/compat only).
     */
    public static function internalKeyFor(string $domain, ?string $product = null): string
    {
        $domain = self::normalizeDomain($domain);
        $product = self::normalizeProduct($product);

        return 'dom:'.$domain.($product !== 'webino' ? ':'.$product : '');
    }

    public static function cacheKey(string $domain, ?string $product = null): string
    {
        return 'license_check:'.md5(self::normalizeDomain($domain).'|'.self::normalizeProduct($product));
    }

    public static function forgetCheckCache(?string $domain, ?string $product = null): void
    {
        if ($domain === null || trim($domain) === '') {
            return;
        }
        foreach (self::relatedDomains($domain) as $host) {
            \Illuminate\Support\Facades\Cache::forget(self::cacheKey($host, $product));
            // Also forget legacy cache key shapes (domain|license_key).
            \Illuminate\Support\Facades\Cache::forget('license_check:'.md5(self::normalizeDomain($host).'|'));
        }
    }
}
