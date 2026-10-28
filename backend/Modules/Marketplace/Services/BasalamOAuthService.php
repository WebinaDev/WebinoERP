<?php

namespace Modules\Marketplace\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\Marketplace\Entities\MarketplaceBasalamConnection;
use Modules\Marketplace\Entities\MarketplaceBasalamSetting;

class BasalamOAuthService
{
    public const DEFAULT_CLIENT_ID = '2357';

    public const DEFAULT_REDIRECT_URI = 'https://webinaagency.ir/api/basalam/oauth/callback'; // must equal the URI registered with Basalam (plugin pins the same)

    public const TOKEN_URL = 'https://auth.basalam.com/oauth/token';

    public const SSO_URL = 'https://basalam.com/accounts/sso';

    public const DEFAULT_SCOPES = 'vendor.product.write vendor.product.read vendor.parcel.write vendor.parcel.read vendor.profile.read vendor.profile.write customer.profile.read customer.profile.write customer.order.read customer.order.write customer.chat.read customer.chat.write customer.wallet.read customer.wallet.write order-processing customer.identity.read';

    public const SESSION_TTL = 600;

    public const HANDOFF_TTL = 180;

    public const DEFAULT_HANDOFF_PATH = '/dashboard/settings/shop/basalam/';

    /**
     * @return array{client_id: string, client_secret: string, redirect_uri: string, scopes: string}
     */
    public function getConfig(): array
    {
        $row = MarketplaceBasalamSetting::query()->first();

        return [
            'client_id' => (string) ($row?->client_id ?: self::DEFAULT_CLIENT_ID),
            // DB first, then env (BASALAM_CLIENT_SECRET) — never required to build/boot.
            'client_secret' => (string) (($row?->client_secret ?? '') ?: (string) env('BASALAM_CLIENT_SECRET', '')),
            'redirect_uri' => (string) ($row?->redirect_uri ?: self::DEFAULT_REDIRECT_URI),
            'scopes' => (string) ($row?->scopes ?: self::DEFAULT_SCOPES),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{client_id: string, client_secret: string, redirect_uri: string, scopes: string}
     */
    public function saveConfig(array $data): array
    {
        $current = $this->getConfig();
        $secret = isset($data['client_secret']) ? (string) $data['client_secret'] : '';
        if ($secret === '' || $secret === '***') {
            $secret = $current['client_secret'];
        }

        $payload = [
            'client_id' => (string) ($data['client_id'] ?? $current['client_id'] ?: self::DEFAULT_CLIENT_ID),
            'client_secret' => $secret,
            'redirect_uri' => (string) ($data['redirect_uri'] ?? $current['redirect_uri'] ?: self::DEFAULT_REDIRECT_URI),
            'scopes' => (string) ($data['scopes'] ?? $current['scopes'] ?: self::DEFAULT_SCOPES),
        ];
        if ($payload['client_id'] === '') {
            $payload['client_id'] = self::DEFAULT_CLIENT_ID;
        }
        if ($payload['redirect_uri'] === '') {
            $payload['redirect_uri'] = self::DEFAULT_REDIRECT_URI;
        }
        if ($payload['scopes'] === '') {
            $payload['scopes'] = self::DEFAULT_SCOPES;
        }

        $row = MarketplaceBasalamSetting::query()->first();
        if ($row) {
            $row->update($payload);
        } else {
            MarketplaceBasalamSetting::query()->create($payload);
        }

        return $this->getConfig();
    }

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $cfg = $this->getConfig();

        return [
            'configured' => $cfg['client_secret'] !== '',
            'client_id' => $cfg['client_id'],
            'client_secret' => $cfg['client_secret'] !== '' ? '***' : '',
            'redirect_uri' => $cfg['redirect_uri'],
            'scopes' => $cfg['scopes'],
            'token_url' => self::TOKEN_URL,
            'sso_url' => self::SSO_URL,
            'callback_path' => '/api/basalam/oauth/callback',
        ];
    }

    public function isReady(): bool
    {
        $cfg = $this->getConfig();

        return $cfg['client_id'] !== '' && $cfg['client_secret'] !== '' && $cfg['redirect_uri'] !== '';
    }

    /**
     * Merchant-side path that receives the signed token handoff (same host as the site).
     */
    public static function normalizeHandoffPath(?string $path): string
    {
        $path = trim((string) $path);
        if ($path === '' || $path[0] !== '/' || str_starts_with($path, '//') || str_contains($path, '..')
            || preg_match('#[\s?\#\\\\]#', $path)) {
            return self::DEFAULT_HANDOFF_PATH;
        }

        return $path;
    }

    /**
     * @return array{url: string, state: string, redirect_uri: string, client_id: string, expires_in: int, handoff_key: string}
     */
    public function start(string $siteUrl, string $returnUrl = '', ?string $handoffPath = null): array
    {
        $siteUrl = rtrim($siteUrl, '/');
        if ($returnUrl !== '') {
            $siteHost = parse_url($siteUrl, PHP_URL_HOST);
            $returnHost = parse_url($returnUrl, PHP_URL_HOST);
            if (! is_string($siteHost) || ! is_string($returnHost) || strcasecmp($siteHost, $returnHost) !== 0) {
                $returnUrl = '';
            }
        }

        $state = Str::random(48);
        $session = [
            'site_url' => $siteUrl,
            'return_url' => $returnUrl,
            'created' => time(),
            'ip' => request()->ip() ?? '',
            'handoff_key' => Str::random(32),
            'handoff_path' => self::normalizeHandoffPath($handoffPath),
        ];
        Cache::put($this->sessionKey($state), $session, self::SESSION_TTL);

        $cfg = $this->getConfig();
        $sso = self::SSO_URL
            .'?client_id='.rawurlencode($cfg['client_id'])
            .'&scope='.rawurlencode($cfg['scopes'])
            .'&redirect_uri='.rawurlencode($cfg['redirect_uri'])
            .'&state='.rawurlencode($state);

        return [
            'url' => $sso,
            'state' => $state,
            'redirect_uri' => $cfg['redirect_uri'],
            'client_id' => $cfg['client_id'],
            'expires_in' => self::SESSION_TTL,
            'handoff_key' => $session['handoff_key'],
        ];
    }

    /**
     * @return array{access_token: string, refresh_token: string, expires_in: int, token_type: string}
     */
    public function refresh(string $refreshToken, ?string $siteUrl = null, ?int $vendorId = null): array
    {
        // Single-flight per refresh token: concurrent callers reuse the first result.
        $key = 'basalam-erp-refresh:'.hash('sha256', $refreshToken);
        if ($cached = \Illuminate\Support\Facades\Cache::get($key.':result')) {
            return $cached;
        }
        $lock = \Illuminate\Support\Facades\Cache::lock($key, 60);
        try {
            $lock->block(20);
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException) {
            throw new \RuntimeException('تمدید توکن باسلام در حال انجام است؛ کمی بعد دوباره تلاش کنید.');
        }
        try {
            if ($cached = \Illuminate\Support\Facades\Cache::get($key.':result')) {
                return $cached;
            }
            $result = $this->doRefresh($refreshToken, $siteUrl, $vendorId);
            \Illuminate\Support\Facades\Cache::put($key.':result', $result, 60);

            return $result;
        } finally {
            $lock->release();
        }
    }

    /**
     * @return array{access_token: string, refresh_token: string, expires_in: int, token_type: string}
     */
    protected function doRefresh(string $refreshToken, ?string $siteUrl = null, ?int $vendorId = null): array
    {
        $cfg = $this->getConfig();
        $token = $this->exchangeToken([
            'grant_type' => 'refresh_token',
            'client_id' => $cfg['client_id'],
            'client_secret' => $cfg['client_secret'],
            'refresh_token' => $refreshToken,
            'redirect_uri' => $cfg['redirect_uri'],
        ]);

        if (is_string($siteUrl) && $siteUrl !== '') {
            $siteUrl = rtrim($siteUrl, '/');
            if ($vendorId && $vendorId > 0) {
                $this->upsertConnection($siteUrl, $vendorId, 'connected', [
                    'access_token' => $token['access_token'] ?? '',
                    'refresh_token' => $token['refresh_token'] ?? $refreshToken,
                    'expires_in' => $token['expires_in'] ?? null,
                    'expires_at' => self::expiresAt($token['expires_in'] ?? null),
                ]);
            } else {
                $existing = MarketplaceBasalamConnection::query()->where('site_url', $siteUrl)->first();
                if ($existing) {
                    $this->upsertConnection($siteUrl, (int) ($existing->vendor_id ?? 0), 'connected', [
                        'access_token' => $token['access_token'] ?? '',
                        'refresh_token' => $token['refresh_token'] ?? $refreshToken,
                        'expires_in' => $token['expires_in'] ?? null,
                    ]);
                }
            }
        }

        return [
            'access_token' => (string) ($token['access_token'] ?? ''),
            'refresh_token' => (string) ($token['refresh_token'] ?? $refreshToken),
            'expires_in' => (int) ($token['expires_in'] ?? 0),
            'expires_at' => self::expiresAt($token['expires_in'] ?? null),
            'token_type' => (string) ($token['token_type'] ?? 'Bearer'),
        ];
    }

    /**
     * Complete OAuth and return merchant handoff URL.
     */
    public function completeAuthorization(string $code, string $state): string
    {
        $key = $this->sessionKey($state);
        $session = Cache::pull($key);
        if (! is_array($session) || empty($session['site_url'])) {
            throw new \RuntimeException(__('messages.oauth_session_expired_or_invalid_start_connection_again_from'));
        }

        $cfg = $this->getConfig();
        $token = $this->exchangeToken([
            'grant_type' => 'authorization_code',
            'client_id' => $cfg['client_id'],
            'client_secret' => $cfg['client_secret'],
            'redirect_uri' => $cfg['redirect_uri'],
            'code' => $code,
        ]);

        $access = (string) ($token['access_token'] ?? '');
        $refresh = (string) ($token['refresh_token'] ?? '');
        $expires = (int) ($token['expires_in'] ?? 0);
        if ($access === '') {
            throw new \RuntimeException(__('messages.basalam_did_not_return_an_access_token'));
        }

        $site = rtrim((string) $session['site_url'], '/');
        $handoffPath = self::normalizeHandoffPath($session['handoff_path'] ?? null);
        $vendorId = $this->resolveVendorId($access);
        if ($vendorId < 1) {
            return $site.$handoffPath.'?oauth=error&reason=vendor';
        }

        $this->upsertConnection($site, $vendorId, 'connected', [
            'access_token' => $access,
            'refresh_token' => $refresh,
            'expires_in' => $expires,
            'expires_at' => self::expiresAt($expires),
        ]);

        $ts = (string) time();
        $handoffKey = (string) ($session['handoff_key'] ?? '');
        $sigPayload = $access.'|'.$refresh.'|'.$ts.'|'.$vendorId.'|'.$site;
        $sig = hash_hmac('sha256', $sigPayload, $handoffKey);

        Cache::put(
            $this->sessionKey('handoff_'.substr($sig, 0, 32)),
            [
                'site_url' => $session['site_url'],
                'return_url' => $session['return_url'] ?? '',
                'created' => time(),
            ],
            self::HANDOFF_TTL
        );

        $query = http_build_query([
            'access_token' => $access,
            'refresh_token' => $refresh,
            'expires_in' => (string) $expires,
            'vendor_id' => (string) $vendorId,
            'is_vendor' => 'true',
            'webino_sig' => $sig,
            'webino_ts' => $ts,
            'webino_hk' => $handoffKey,
            'expires_at' => (string) (self::expiresAt($expires) ?? ''),
            'oauth' => 'handoff',
            'return_url' => ! empty($session['return_url']) ? (string) $session['return_url'] : null,
        ]);

        // v2: signed with the per-site Site-Auth secret (never in the URL) when the site is enrolled.
        $siteSecret = \Modules\Core\Services\SiteAuth::secretFor((string) parse_url($site, PHP_URL_HOST));
        if ($siteSecret !== '') {
            $nonce = Str::random(24);
            $query .= '&'.http_build_query([
                'webino_v' => '2',
                'webino_nonce' => $nonce,
                'webino_sig2' => self::handoffSignatureV2($siteSecret, (string) parse_url($site, PHP_URL_HOST), $vendorId, $ts, $nonce, $access, $refresh, $expires),
            ]);
        }

        $handoff = $site.$handoffPath.'?'.$query;
        $sessionHost = parse_url($site, PHP_URL_HOST);
        $handoffHost = parse_url($handoff, PHP_URL_HOST);
        if (! is_string($sessionHost) || ! is_string($handoffHost) || strcasecmp($sessionHost, $handoffHost) !== 0) {
            throw new \RuntimeException(__('messages.merchant_handoff_host_mismatch'));
        }

        return $handoff;
    }

    /**
     * @param  array<string, mixed>|null  $tokens
     */
    public function upsertConnection(string $siteUrl, int $vendorId, string $status = 'connected', ?array $tokens = null): MarketplaceBasalamConnection
    {
        $siteUrl = rtrim($siteUrl, '/');
        $status = in_array($status, ['connected', 'disconnected'], true) ? $status : 'connected';
        $row = MarketplaceBasalamConnection::query()->firstOrNew(['site_url' => $siteUrl]);
        $now = now();

        if ($vendorId > 0) {
            $row->vendor_id = $vendorId;
        }
        $row->status = $status;
        $row->last_seen_at = $now;
        if ($status === 'connected') {
            if (! $row->connected_at) {
                $row->connected_at = $now;
            }
            $row->disconnected_at = null;
        } else {
            $row->disconnected_at = $now;
        }
        if ($tokens !== null) {
            $row->tokens = $tokens;
        }
        $row->save();

        return $row->fresh();
    }

    public function markDisconnected(string $siteUrl): MarketplaceBasalamConnection
    {
        $siteUrl = rtrim($siteUrl, '/');
        $existing = MarketplaceBasalamConnection::query()->where('site_url', $siteUrl)->first();

        return $this->upsertConnection($siteUrl, (int) ($existing?->vendor_id ?? 0), 'disconnected');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listConnections(): array
    {
        return MarketplaceBasalamConnection::query()
            ->orderByDesc('last_seen_at')
            ->get()
            ->map(fn (MarketplaceBasalamConnection $row) => [
                'site_url' => $row->site_url,
                'vendor_id' => $row->vendor_id,
                'status' => $row->status,
                'connected_at' => optional($row->connected_at)?->toIso8601String(),
                'last_seen_at' => optional($row->last_seen_at)?->toIso8601String(),
                'disconnected_at' => optional($row->disconnected_at)?->toIso8601String(),
                'webhook_url' => self::webhookUrl((string) $row->site_url),
                'legacy_webhook_url' => self::webhookUrl((string) $row->site_url, true),
                'site_auth_enrolled' => \Modules\Core\Services\SiteAuth::isEnrolled((string) parse_url((string) $row->site_url, PHP_URL_HOST)),
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    protected function exchangeToken(array $body): array
    {
        try {
            $response = Http::timeout(30)
                ->retry(2, 500, fn ($e) => $e instanceof \Illuminate\Http\Client\ConnectionException, throw: false)
                ->acceptJson()
                ->asJson()
                ->post(self::TOKEN_URL, $body);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw new \RuntimeException(self::humanizeTransport($e->getMessage()));
        }

        $data = $response->json();
        if (! is_array($data) || empty($data['access_token']) || ! $response->successful()) {
            $message = is_array($data)
                ? (string) ($data['error_description'] ?? $data['message'] ?? 'Token exchange failed.')
                : 'Token exchange failed.';
            throw new \RuntimeException($message);
        }

        return $data;
    }

    /** Persian message for transport failures (plugin v0.9.65); raw cURL text is never shown. */
    public static function humanizeTransport(string $raw): string
    {
        if (preg_match('/cURL error (\d+)/i', $raw, $m)) {
            switch ((int) $m[1]) {
                case 18:
                case 52:
                case 56:
                    return 'پاسخ سرور باسلام ناقص رسید (احتمالاً CDN پاسخ را قطع کرده). چند دقیقه بعد دوباره تلاش کنید.';
                case 28:
                    return 'سرور باسلام در زمان مقرر پاسخ نداد. چند دقیقه بعد دوباره تلاش کنید.';
                case 6:
                    return 'نشانی سرور باسلام پیدا نشد (خطای DNS).';
                case 7:
                    return 'اتصال به سرور باسلام برقرار نشد؛ ممکن است فایروال درخواست‌های خروجی را مسدود کرده باشد.';
                case 35:
                case 60:
                    return 'ارتباط امن (SSL) با سرور باسلام برقرار نشد.';
            }
        }

        return 'ارتباط با سرور باسلام برقرار نشد. چند دقیقه بعد دوباره تلاش کنید.';
    }

    protected function resolveVendorId(string $accessToken): int
    {
        try {
            $response = Http::timeout(20)
                ->withToken($accessToken)
                ->acceptJson()
                ->withHeaders(['user-agent' => 'WebinoERP-Basalam-OAuth'])
                ->get('https://openapi.basalam.com/v1/users/me');
            if (! $response->successful()) {
                return 0;
            }
            $data = $response->json();
            if (! is_array($data)) {
                return 0;
            }
            $vendor = $data['vendor'] ?? ($data['data']['vendor'] ?? null);
            if (is_array($vendor) && isset($vendor['id'])) {
                return (int) $vendor['id'];
            }
        } catch (\Throwable) {
            return 0;
        }

        return 0;
    }

    /** Site endpoint Basalam webhooks are registered to (by the plugin, not ERP). */
    public static function webhookUrl(string $siteUrl, bool $legacy = false): string
    {
        $path = $legacy
            ? (string) config('integrations.basalam.legacy_webhook_path', '/wp-json/sync-basalam/v1/order-manager')
            : (string) config('integrations.basalam.webhook_path', '/wp-json/webino-basalam/v1/order-manager');

        return rtrim($siteUrl, '/').'/'.ltrim($path, '/');
    }

    public static function expiresAt(mixed $expiresIn): ?int
    {
        $n = (int) $expiresIn;

        return $n > 0 ? time() + $n : null;
    }

    /**
     * hex(HMAC-SHA256(site_secret, "v2\nbasalam-handoff\n{domain}\n{vendor_id}\n{ts}\n{nonce}\n{expires_in}\n" . sha256(access_token."|".refresh_token)))
     * domain = normalized host (lowercase, no www).
     */
    public static function handoffSignatureV2(string $secret, string $host, int $vendorId, string $ts, string $nonce, string $access, string $refresh, int $expiresIn): string
    {
        $domain = \Modules\Core\Services\CoreLicenseResolver::normalizeDomain($host);
        $msg = implode("\n", ['v2', 'basalam-handoff', $domain, (string) $vendorId, $ts, $nonce, (string) $expiresIn, hash('sha256', $access.'|'.$refresh)]);

        return hash_hmac('sha256', $msg, $secret);
    }

    protected function sessionKey(string $state): string
    {
        return 'marketplace_basalam_oauth_'.$state;
    }
}
