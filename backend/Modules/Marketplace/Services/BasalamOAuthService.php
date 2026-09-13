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

    public const DEFAULT_REDIRECT_URI = 'https://webina.dev/api/basalam/oauth/callback';

    public const TOKEN_URL = 'https://auth.basalam.com/oauth/token';

    public const SSO_URL = 'https://basalam.com/accounts/sso';

    public const DEFAULT_SCOPES = 'vendor.product.write vendor.product.read vendor.parcel.write vendor.parcel.read vendor.profile.read vendor.profile.write customer.profile.read customer.profile.write customer.order.read customer.order.write customer.chat.read customer.chat.write customer.wallet.read customer.wallet.write order-processing customer.identity.read';

    public const SESSION_TTL = 600;

    public const HANDOFF_TTL = 180;

    /**
     * @return array{client_id: string, client_secret: string, redirect_uri: string, scopes: string}
     */
    public function getConfig(): array
    {
        $row = MarketplaceBasalamSetting::query()->first();

        return [
            'client_id' => (string) ($row?->client_id ?: self::DEFAULT_CLIENT_ID),
            'client_secret' => (string) ($row?->client_secret ?? ''),
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
     * @return array{url: string, state: string, redirect_uri: string, client_id: string, expires_in: int}
     */
    public function start(string $siteUrl, string $returnUrl = ''): array
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
        ];
    }

    /**
     * @return array{access_token: string, refresh_token: string, expires_in: int, token_type: string}
     */
    public function refresh(string $refreshToken, ?string $siteUrl = null, ?int $vendorId = null): array
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
                ]);
            } else {
                $existing = MarketplaceBasalamConnection::query()->where('site_url', $siteUrl)->first();
                if ($existing) {
                    $this->upsertConnection($siteUrl, (int) ($existing->vendor_id ?? 0), 'connected');
                }
            }
        }

        return [
            'access_token' => (string) ($token['access_token'] ?? ''),
            'refresh_token' => (string) ($token['refresh_token'] ?? $refreshToken),
            'expires_in' => (int) ($token['expires_in'] ?? 0),
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
            throw new \RuntimeException('OAuth session expired or invalid. Start connection again from your store dashboard.');
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
            throw new \RuntimeException('Basalam did not return an access token.');
        }

        $site = rtrim((string) $session['site_url'], '/');
        $vendorId = $this->resolveVendorId($access);
        if ($vendorId < 1) {
            return $site.'/dashboard/settings/shop/basalam/?oauth=error&reason=vendor';
        }

        $this->upsertConnection($site, $vendorId, 'connected', [
            'access_token' => $access,
            'refresh_token' => $refresh,
            'expires_in' => $expires,
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
            'oauth' => 'handoff',
            'return_url' => ! empty($session['return_url']) ? (string) $session['return_url'] : null,
        ]);

        $handoff = $site.'/dashboard/settings/shop/basalam/?'.$query;
        $sessionHost = parse_url($site, PHP_URL_HOST);
        $handoffHost = parse_url($handoff, PHP_URL_HOST);
        if (! is_string($sessionHost) || ! is_string($handoffHost) || strcasecmp($sessionHost, $handoffHost) !== 0) {
            throw new \RuntimeException('Merchant handoff host mismatch.');
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
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    protected function exchangeToken(array $body): array
    {
        $response = Http::timeout(30)
            ->acceptJson()
            ->asJson()
            ->post(self::TOKEN_URL, $body);

        $data = $response->json();
        if (! is_array($data) || empty($data['access_token']) || ! $response->successful()) {
            $message = is_array($data)
                ? (string) ($data['error_description'] ?? $data['message'] ?? 'Token exchange failed.')
                : 'Token exchange failed.';
            throw new \RuntimeException($message);
        }

        return $data;
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

    protected function sessionKey(string $state): string
    {
        return 'marketplace_basalam_oauth_'.$state;
    }
}
