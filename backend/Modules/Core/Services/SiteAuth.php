<?php

namespace Modules\Core\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\Integrations\Entities\IntegrationSetting;

/**
 * Per-site HMAC auth for WP Dashboard → ERP Edge calls.
 *
 * Headers: X-Webino-Site (domain), X-Webino-Ts, X-Webino-Nonce, X-Webino-Signature
 * signature = hex(HMAC-SHA256(secret, "v1\nMETHOD\n/path\ndomain\nts\nnonce\nsha256(body)"))
 *
 * Modes (config integrations.site_auth.mode):
 *  - off:     never checked
 *  - grace:   (default) enrolled domains must sign; unenrolled domains pass (logged)
 *  - enforce: every request must be signed by an enrolled domain
 */
class SiteAuth
{
    public const SETTING_GROUP = 'site_auth';

    public const TS_WINDOW = 300;

    public static function mode(): string
    {
        $mode = strtolower((string) config('integrations.site_auth.mode', 'grace'));

        return in_array($mode, ['off', 'grace', 'enforce'], true) ? $mode : 'grace';
    }

    public static function secretFor(string $domain): string
    {
        $domain = CoreLicenseResolver::normalizeDomain($domain);
        if ($domain === '') {
            return '';
        }
        $stored = IntegrationSetting::getString(self::SETTING_GROUP, 'secret:'.$domain, '');
        if ($stored === '') {
            return '';
        }
        try {
            return (string) decrypt($stored);
        } catch (\Throwable) {
            return '';
        }
    }

    public static function storeSecret(string $domain, string $secret): void
    {
        $domain = CoreLicenseResolver::normalizeDomain($domain);
        IntegrationSetting::putString(self::SETTING_GROUP, 'secret:'.$domain, encrypt($secret));
        IntegrationSetting::putString(self::SETTING_GROUP, 'enrolled_at:'.$domain, now()->toIso8601String());
    }

    public static function forget(string $domain): void
    {
        $domain = CoreLicenseResolver::normalizeDomain($domain);
        IntegrationSetting::query()->where('integration', self::SETTING_GROUP)
            ->whereIn('key', ['secret:'.$domain, 'enrolled_at:'.$domain])->delete();
    }

    public static function isEnrolled(string $domain): bool
    {
        return self::secretFor($domain) !== '';
    }

    public static function canonical(string $method, string $path, string $domain, string $ts, string $nonce, string $body): string
    {
        return implode("\n", ['v1', strtoupper($method), '/'.ltrim($path, '/'), $domain, $ts, $nonce, hash('sha256', $body)]);
    }

    public static function sign(string $secret, string $method, string $path, string $domain, string $ts, string $nonce, string $body): string
    {
        return hash_hmac('sha256', self::canonical($method, $path, $domain, $ts, $nonce, $body), $secret);
    }

    public static function hasSignature(Request $request): bool
    {
        return (string) $request->header('X-Webino-Signature', '') !== '';
    }

    /**
     * Verify the signature headers. Returns the authenticated domain, or null.
     * A nonce is accepted once (replay protection).
     */
    public static function verifiedDomain(Request $request): ?string
    {
        if ($request->attributes->has('webino_site_domain')) {
            return $request->attributes->get('webino_site_domain');
        }
        $domain = CoreLicenseResolver::normalizeDomain((string) $request->header('X-Webino-Site', ''));
        $ts = (string) $request->header('X-Webino-Ts', '');
        $nonce = (string) $request->header('X-Webino-Nonce', '');
        $sig = strtolower((string) $request->header('X-Webino-Signature', ''));
        if ($domain === '' || $sig === '' || ! ctype_digit($ts) || ! preg_match('/^[A-Za-z0-9_-]{16,128}$/', $nonce)) {
            return null;
        }
        if (abs(time() - (int) $ts) > self::TS_WINDOW) {
            return null;
        }
        $secret = self::secretFor($domain);
        if ($secret === '') {
            return null;
        }
        $expected = self::sign($secret, $request->getMethod(), $request->getPathInfo(), $domain, $ts, $nonce, (string) $request->getContent());
        if (! hash_equals($expected, $sig)) {
            return null;
        }
        if (! Cache::add('site_auth:nonce:'.$domain.':'.$nonce, 1, now()->addSeconds(self::TS_WINDOW * 2))) {
            return null; // replay
        }
        $request->attributes->set('webino_site_domain', $domain);

        return $domain;
    }

    /**
     * Decide whether a request that claims $claimedDomain may proceed.
     *
     * @return array{ok: bool, reason?: string, domain?: string}
     */
    public static function authorize(Request $request, string $claimedDomain): array
    {
        $mode = self::mode();
        $claimed = CoreLicenseResolver::normalizeDomain($claimedDomain);
        if ($mode === 'off') {
            return ['ok' => true, 'domain' => $claimed];
        }

        if (self::hasSignature($request)) {
            $signed = self::verifiedDomain($request);
            if ($signed === null) {
                return ['ok' => false, 'reason' => 'invalid_signature'];
            }
            if ($claimed !== '' && $claimed !== $signed) {
                return ['ok' => false, 'reason' => 'domain_mismatch'];
            }

            return ['ok' => true, 'domain' => $signed];
        }

        // Unsigned request.
        if ($mode === 'enforce') {
            return ['ok' => false, 'reason' => 'signature_required'];
        }
        if ($claimed !== '' && self::isEnrolled($claimed)) {
            // Upgraded sites always sign; an unsigned call for an enrolled domain is not from that site.
            return ['ok' => false, 'reason' => 'signature_required'];
        }
        Log::info('site_auth.unsigned_grace', ['domain' => $claimed, 'path' => $request->path()]);

        return ['ok' => true, 'domain' => $claimed];
    }
}
