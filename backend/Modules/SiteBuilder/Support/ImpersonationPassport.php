<?php

namespace Modules\SiteBuilder\Support;

use Illuminate\Support\Facades\Cache;
use Modules\SiteBuilder\Exceptions\SiteImpersonationException;

/**
 * Short-lived HMAC passport proving an ERP staff member may hop between
 * customer sites. The tenant stores it server-side and exchanges it at ERP;
 * it is never a customer password.
 */
final class ImpersonationPassport
{
    public const TTL_SECONDS = 1200;

    public static function issue(int $staffId, int $ttlSeconds = self::TTL_SECONDS): string
    {
        $exp = time() + $ttlSeconds;
        $jti = bin2hex(random_bytes(16));
        $body = self::encode([
            'v' => 1,
            'sid' => $staffId,
            'exp' => $exp,
            'jti' => $jti,
        ]);
        $token = $body.'.'.self::b64(self::sign($body));
        $ttl = max(1, $exp - time());
        Cache::put(self::cacheKey($jti), ['staff_id' => $staffId], $ttl);

        return $token;
    }

    /**
     * @return array{sid: int, exp: int, jti: string}
     */
    public static function parse(string $token): array
    {
        $token = trim($token);
        if ($token === '' || strlen($token) > 1500) {
            throw new SiteImpersonationException('نشست جانشینی نامعتبر یا منقضی شده است.', 401);
        }

        $parts = explode('.', $token, 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new SiteImpersonationException('نشست جانشینی نامعتبر یا منقضی شده است.', 401);
        }

        [$body, $sig] = $parts;
        $expected = self::sign($body);
        $given = self::b64d($sig);
        if ($given === null || strlen($given) !== strlen($expected) || ! hash_equals($expected, $given)) {
            throw new SiteImpersonationException('نشست جانشینی نامعتبر یا منقضی شده است.', 401);
        }

        $json = self::b64d($body);
        $claims = is_string($json) ? json_decode($json, true) : null;
        if (! is_array($claims) || (int) ($claims['v'] ?? 0) !== 1) {
            throw new SiteImpersonationException('نشست جانشینی نامعتبر یا منقضی شده است.', 401);
        }

        $sid = (int) ($claims['sid'] ?? 0);
        $exp = (int) ($claims['exp'] ?? 0);
        $jti = (string) ($claims['jti'] ?? '');
        if ($sid < 1 || $exp < time() || ! preg_match('/^[a-f0-9]{32}$/', $jti)) {
            throw new SiteImpersonationException('نشست جانشینی نامعتبر یا منقضی شده است.', 401);
        }

        $stored = Cache::get(self::cacheKey($jti));
        if (! is_array($stored) || (int) ($stored['staff_id'] ?? 0) !== $sid) {
            throw new SiteImpersonationException('نشست جانشینی نامعتبر یا منقضی شده است.', 401);
        }

        return ['sid' => $sid, 'exp' => $exp, 'jti' => $jti];
    }

    public static function revoke(string $token): void
    {
        try {
            $claims = self::parse($token);
        } catch (SiteImpersonationException) {
            return;
        }

        Cache::forget(self::cacheKey($claims['jti']));
    }

    private static function cacheKey(string $jti): string
    {
        return 'site_impersonation:'.$jti;
    }

    /**
     * @param  array<string, int|string>  $claims
     */
    private static function encode(array $claims): string
    {
        $json = json_encode($claims, JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new SiteImpersonationException('ساخت نشست جانشینی ناموفق بود.', 500);
        }

        return self::b64($json);
    }

    private static function sign(string $body): string
    {
        return hash_hmac('sha256', $body, (string) config('app.key'), true);
    }

    private static function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private static function b64d(string $encoded): ?string
    {
        if ($encoded === '' || preg_match('/[^A-Za-z0-9\-_]/', $encoded)) {
            return null;
        }
        $pad = strlen($encoded) % 4;
        if ($pad > 0) {
            $encoded .= str_repeat('=', 4 - $pad);
        }
        $raw = base64_decode(strtr($encoded, '-_', '+/'), true);

        return is_string($raw) ? $raw : null;
    }
}
