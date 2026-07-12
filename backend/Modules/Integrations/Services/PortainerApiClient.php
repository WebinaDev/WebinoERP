<?php

namespace Modules\Integrations\Services;

use Illuminate\Support\Facades\Http;
use Modules\Core\Entities\CoreHostingSetting;
use RuntimeException;

class PortainerApiClient
{
    public function __construct(protected CoreHostingSetting $settings) {}

    public static function fromCurrentSettings(): ?self
    {
        $s = CoreHostingSetting::current();
        $base = $s->portainer_url;
        $token = $s->portainer_api_token;
        if (! is_string($base) || $base === '' || ! is_string($token) || $token === '') {
            return null;
        }

        return new self($s);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function stacks(?int $endpointId = null): array
    {
        $res = Http::withToken((string) $this->settings->portainer_api_token)
            ->timeout(45)
            ->acceptJson()
            ->get($this->api('/stacks'));

        if (! $res->successful()) {
            throw new RuntimeException('Portainer stacks list failed: '.$res->body());
        }

        $json = $res->json();
        if (! is_array($json)) {
            return [];
        }

        $endpointId = $endpointId ?? $this->settings->portainer_endpoint_id;
        if ($endpointId === null || $endpointId <= 0) {
            return $json;
        }

        return array_values(array_filter($json, function ($row) use ($endpointId) {
            return is_array($row) && (int) ($row['EndpointId'] ?? $row['endpointId'] ?? 0) === $endpointId;
        }));
    }

    public function stackStart(int $stackId, int $endpointId): void
    {
        $this->stackVerb($stackId, $endpointId, 'start');
    }

    public function stackStop(int $stackId, int $endpointId): void
    {
        $this->stackVerb($stackId, $endpointId, 'stop');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function endpoints(): array
    {
        $res = Http::withToken((string) $this->settings->portainer_api_token)
            ->timeout(45)
            ->acceptJson()
            ->get($this->api('/endpoints'));

        if (! $res->successful()) {
            throw new RuntimeException('Portainer endpoints list failed: '.$res->body());
        }

        $json = $res->json();

        return is_array($json) ? $json : [];
    }

    protected function stackVerb(int $stackId, int $endpointId, string $verb): void
    {
        $url = $this->api('/stacks/'.$stackId.'/'.$verb).'?endpointId='.$endpointId;
        $res = Http::withToken((string) $this->settings->portainer_api_token)
            ->timeout(120)
            ->acceptJson()
            ->asJson()
            ->post($url, []);

        if (! $res->successful()) {
            throw new RuntimeException('Portainer '.$verb.' failed: '.$res->body());
        }
    }

    protected function api(string $path): string
    {
        $base = rtrim((string) $this->settings->portainer_url, '/');

        return $base.'/api'.(str_starts_with($path, '/') ? $path : '/'.$path);
    }
}
