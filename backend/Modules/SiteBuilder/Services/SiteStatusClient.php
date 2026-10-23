<?php

namespace Modules\SiteBuilder\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Modules\Core\Entities\CoreHostingSetting;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;
use RuntimeException;

/**
 * Talks to the tenant contract GET/PUT https://<domain>/api/v1/provision/site-status.
 * Auth mirrors SiteProvisionOrchestrator::bootstrapRemoteSite(): X-Provision-Token (the site's
 * TENANT_PROVISION_TOKEN) + X-Provision-Signature = HMAC-SHA256(raw body, provision_webhook_secret).
 * GET requests sign the empty string.
 */
class SiteStatusClient
{
    public const PATH = '/api/v1/provision/site-status';

    /** @return array<string, mixed> */
    public function get(WebinoSiteProvision $provision): array
    {
        return $this->send($provision, 'GET', '');
    }

    /**
     * @param  array{status:string,reason?:?string,expires_at?:?string,launch_at?:?string,renew_url?:?string,support_url?:?string}  $payload
     * @return array<string, mixed>
     */
    public function put(WebinoSiteProvision $provision, array $payload): array
    {
        if (! in_array($payload['status'] ?? null, WebinoSiteProvision::SITE_STATUSES, true)) {
            throw new RuntimeException('invalid site status');
        }
        $body = json_encode(array_filter($payload, fn ($v) => $v !== null), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $this->send($provision, 'PUT', (string) $body);
    }

    /** @return array<string, mixed> */
    protected function send(WebinoSiteProvision $provision, string $method, string $body): array
    {
        $domain = strtolower(trim((string) $provision->domain));
        $token = (string) ($provision->provision_token ?? '');
        if ($domain === '' || ! str_contains($domain, '.')) {
            throw new RuntimeException('platform.invalid_domain');
        }
        if ($token === '') {
            throw new RuntimeException('provision token missing');
        }
        $secret = '';
        try {
            $secret = (string) (CoreHostingSetting::current()->provision_webhook_secret ?? '');
        } catch (\Throwable) {
        }
        if ($secret === '') {
            throw new RuntimeException('platform.provision_hmac_missing');
        }

        $request = Http::withHeaders([
            'X-Provision-Token' => $token,
            'X-Provision-Signature' => hash_hmac('sha256', $body, $secret),
            'Accept' => 'application/json',
        ])
            ->timeout((int) config('site_builder.site_status_timeout', 10))
            ->connectTimeout(5)
            ->retry(3, 500, function ($e) {
                // retry network errors and 5xx only; 4xx is a contract/auth problem
                return $e instanceof ConnectionException
                    || ($e instanceof RequestException && $e->response->serverError());
            }, throw: true);

        $url = 'https://'.$domain.self::PATH;
        $response = $method === 'GET'
            ? $request->get($url)
            : $request->withBody($body, 'application/json')->put($url);
        $response->throw();

        $json = $response->json();
        $data = is_array($json['data'] ?? null) ? $json['data'] : (is_array($json) ? $json : []);

        return [
            'status' => $data['status'] ?? null,
            'reason' => $data['reason'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'launch_at' => $data['launch_at'] ?? null,
            'updated_at' => $data['updated_at'] ?? null,
            'updated_by' => $data['updated_by'] ?? null,
        ];
    }
}
