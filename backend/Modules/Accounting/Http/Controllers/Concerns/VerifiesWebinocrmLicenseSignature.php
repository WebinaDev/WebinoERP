<?php

namespace Modules\Accounting\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Modules\Core\Services\CoreLicenseResolver;

trait VerifiesWebinocrmLicenseSignature
{
    protected function licenseHmacSecret(): string
    {
        return (string) (config('app.webinocrm_license_hmac_secret') ?? '');
    }

    /**
     * HMAC auth for service-to-service calls (NOT a license code).
     * Canonical payload: domain|{product}|ts
     * Legacy WP / old Dashboard: domain|{license_key}|ts (license_key ignored for entitlement)
     *
     * When ERP has no HMAC secret configured, unsigned requests are accepted
     * (domain entitlement does not depend on a tenant-side secret).
     */
    protected function verifyLicenseRequest(Request $request): bool
    {
        $secret = $this->licenseHmacSecret();
        if ($secret === '') {
            // Optional service auth: no shared secret → allow domain-based calls.
            return true;
        }
        $domain = CoreLicenseResolver::normalizeDomain((string) $request->input('domain', ''));
        $product = CoreLicenseResolver::normalizeProduct(
            $request->input('product', $request->input('product_slug'))
        );
        $legacyKey = (string) $request->input('license_key', '');
        // WP compat: if clients still post license_key that looks like a domain, treat as domain echo.
        if ($legacyKey !== '' && CoreLicenseResolver::normalizeDomain($legacyKey) === $domain) {
            $legacyKey = $domain;
        }
        $ts = (int) $request->input('ts', 0);
        $sig = (string) $request->input('signature', '');
        if ($sig === '' || abs(time() - $ts) > 600) {
            return false;
        }

        $candidates = array_unique([
            $domain.'|'.$product.'|'.$ts,
            $domain.'|'.'|'.$ts,
            $domain.'|'.$legacyKey.'|'.$ts,
            $domain.'|'.$domain.'|'.$ts,
        ]);

        foreach ($candidates as $payload) {
            $expect = hash_hmac('sha256', $payload, $secret);
            if (hash_equals($expect, $sig)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Domain-status / entitlement check may be unsigned (public, rate-limited).
     * If a signature is present, it must still verify when a secret is configured.
     */
    protected function authorizeLicenseCheck(Request $request): bool
    {
        $secret = $this->licenseHmacSecret();
        $sig = (string) $request->input('signature', '');

        if ($sig === '') {
            // Public domain-status path — no per-tenant HMAC required.
            return true;
        }

        if ($secret === '') {
            // Signature sent but ERP has no secret: ignore signature, allow domain lookup.
            return true;
        }

        return $this->verifyLicenseRequest($request);
    }
}
