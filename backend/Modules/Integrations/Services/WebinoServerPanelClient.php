<?php

namespace Modules\Integrations\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Modules\Core\Entities\CoreHostingSetting;
use RuntimeException;

class WebinoServerPanelClient
{
    public function isConfigured(): bool
    {
        $s = CoreHostingSetting::current();

        return filled($s->webinoserver_panel_url) && filled($s->webinoserver_api_token);
    }

    /**
     * @return array<string, mixed>
     */
    public function ensureProductInstalled(string $product = 'Webino', ?string $channel = null): array
    {
        $channel = $channel ?? CoreHostingSetting::current()->default_product_channel ?? 'LTS';

        return $this->request()->post('/api/v1/products/install', [
            'product' => $product,
            'channel' => $channel,
        ])->throw()->json();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createSite(array $payload): array
    {
        return $this->request()->post('/api/v1/sites', $payload)->throw()->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function listSites(): array
    {
        return $this->request()->get('/api/v1/sites')->throw()->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function getPlatformStatus(): array
    {
        return $this->request()->get('/api/v1/platform/status')->throw()->json();
    }

    public function deleteSite(string $slug): array
    {
        return $this->request()->delete('/api/v1/sites/'.urlencode($slug))->throw()->json();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createDnsRecord(array $payload): array
    {
        return $this->request()->post('/api/v1/dns/zones/'.$payload['zone_id'].'/records', $payload['record'] ?? $payload)
            ->throw()->json();
    }

    /**
     * Expected Sanctum abilities on the panel token (operators must grant these when creating the token).
     *
     * @return list<string>
     */
    public function requiredAbilities(): array
    {
        /** @var list<string> $abilities */
        $abilities = config('services.webinoserver.required_abilities', ['platform.manage', 'domains.manage']);

        return $abilities;
    }

    protected function request(): PendingRequest
    {
        $s = CoreHostingSetting::current();
        $base = rtrim((string) $s->webinoserver_panel_url, '/');
        // Use the stored Sanctum personal access token as-is (plain text). Do not wrap,
        // hash, or rewrite it — EnforceTokenAbilities on the panel expects the raw token
        // with abilities such as platform.manage + domains.manage (see config services.webinoserver.required_abilities).
        $token = (string) $s->webinoserver_api_token;

        if ($base === '' || $token === '') {
            throw new RuntimeException('WebinoServer panel is not configured.');
        }

        return Http::baseUrl($base)
            ->withToken($token)
            ->acceptJson()
            ->timeout(180);
    }
}
