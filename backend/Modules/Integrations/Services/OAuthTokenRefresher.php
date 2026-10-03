<?php

namespace Modules\Integrations\Services;

use App\Support\ModuleMonitor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class OAuthTokenRefresher
{
    /**
     * Refresh a Google or Microsoft token when it is inside the skew window.
     * Rotated refresh tokens are stored. Repeated failures mark the account needs_reauth.
     */
    public function ensureFresh(Model $account, int $skewSeconds = 120): Model
    {
        $provider = $this->provider($account);
        if ($provider === null) {
            return $account;
        }
        if ($account->expires_at && $account->expires_at->gt(now()->addSeconds($skewSeconds))) {
            return $account;
        }
        if (! $account->refresh_token) {
            return $this->fail($account, 'missing_refresh_token');
        }

        $lock = Cache::lock('oauth:'.$account->getTable().':'.$account->id, 20);
        try {
            $lock->block(5);
        } catch (\Throwable) {
            return $account->fresh() ?? $account;
        }

        try {
            $account->refresh();
            if ($account->expires_at && $account->expires_at->gt(now()->addSeconds($skewSeconds))) {
                return $account;
            }
            $response = $this->request($provider, (string) $account->refresh_token);
            if (! $response->successful() || ! $response->json('access_token')) {
                return $this->fail($account, 'http_'.$response->status());
            }
            $update = [
                'access_token' => (string) $response->json('access_token'),
                'expires_at' => now()->addSeconds((int) $response->json('expires_in', 3600)),
                'refresh_attempts' => 0,
                'last_refresh_error' => null,
                'status' => 'active',
            ];
            if ($response->json('refresh_token')) {
                $update['refresh_token'] = (string) $response->json('refresh_token');
            }
            $account->update($update);
            ModuleMonitor::hook('integrations', 'oauth_refreshed', [
                'account' => $account->getTable().':'.$account->id,
                'provider' => $provider,
            ]);

            return $account->fresh();
        } finally {
            $lock->release();
        }
    }

    private function provider(Model $account): ?string
    {
        return match ((string) $account->provider) {
            'google' => 'google',
            'outlook', 'microsoft' => 'microsoft',
            default => null,
        };
    }

    private function request(string $provider, string $refreshToken): \Illuminate\Http\Client\Response
    {
        if ($provider === 'google') {
            return Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', [
                'client_id' => (string) config('integrations.google.client_id'),
                'client_secret' => (string) config('integrations.google.client_secret'),
                'refresh_token' => $refreshToken,
                'grant_type' => 'refresh_token',
            ]);
        }
        $tenant = (string) config('integrations.microsoft.tenant', 'common');

        return Http::asForm()->timeout(20)->post('https://login.microsoftonline.com/'.$tenant.'/oauth2/v2.0/token', [
            'client_id' => (string) config('integrations.microsoft.client_id'),
            'client_secret' => (string) config('integrations.microsoft.client_secret'),
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);
    }

    private function fail(Model $account, string $reason): Model
    {
        $attempts = (int) $account->refresh_attempts + 1;
        $account->update([
            'refresh_attempts' => $attempts,
            'last_refresh_error' => $reason,
            'status' => $attempts >= 3 ? 'needs_reauth' : ($account->status ?: 'active'),
        ]);
        ModuleMonitor::hook('integrations', 'oauth_refresh_failed', [
            'account' => $account->getTable().':'.$account->id,
            'reason' => $reason,
            'attempts' => $attempts,
        ]);

        $fresh = $account->fresh() ?? $account;
        if ($attempts >= 3) {
            throw new \RuntimeException('oauth_needs_reauth');
        }

        return $fresh;
    }
}
