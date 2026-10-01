<?php

namespace Modules\Core\Services;

use Modules\Core\Entities\CoreLicense;

/**
 * Domain (+ product) is the license identity. license_key is never required for entitlement.
 */
final class CoreLicenseResolver
{
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
     * Resolve entitlement row by domain (+ optional product).
     * Never filters by license_key. If product-specific row missing, falls back to product=webino then any domain row.
     */
    public static function find(string $domain, ?string $product = null): ?CoreLicense
    {
        $domain = self::normalizeDomain($domain);
        if ($domain === '') {
            return null;
        }

        $product = self::normalizeProduct($product);

        $base = CoreLicense::query()->where('domain', $domain);

        $exact = (clone $base)->where('product', $product)->orderByDesc('id')->first();
        if ($exact) {
            return $exact;
        }

        if ($product !== 'webino') {
            $fallback = (clone $base)->where('product', 'webino')->orderByDesc('id')->first();
            if ($fallback) {
                return $fallback;
            }
        }

        // Pre-migration rows (no product column match) or legacy single-domain licenses.
        return (clone $base)->orderByDesc('id')->first();
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
        \Illuminate\Support\Facades\Cache::forget(self::cacheKey($domain, $product));
        // Also forget legacy cache key shapes (domain|license_key).
        \Illuminate\Support\Facades\Cache::forget('license_check:'.md5(self::normalizeDomain($domain).'|'));
    }
}
