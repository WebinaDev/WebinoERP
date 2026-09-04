<?php

namespace Modules\SiteBuilder\Services;

use Illuminate\Support\Facades\Http;
use Modules\Core\Entities\CoreHostingSetting;
use Modules\Platform\Entities\PlatformServer;
use Modules\Platform\Services\LocalSameVpsProvisioner;
use Modules\Platform\Services\WebinoDashboardProvisioner;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;
use Modules\SiteBuilder\Support\ProvisionProgress;
use Throwable;

class SiteProvisionOrchestrator
{
    public function __construct(
        private readonly WebinoDashboardProvisioner $remote,
        private readonly LocalSameVpsProvisioner $local,
        private readonly LicenseProvisionerService $licenses,
        private readonly SiteProvisionAuditLogger $audit,
    ) {}

    public function launch(WebinoSiteProvision $provision): WebinoSiteProvision
    {
        $provision->load(['package.businessType.category', 'package.features', 'crmAccount']);

        if ($provision->status === WebinoSiteProvision::STATUS_CANCELLED) {
            return $provision;
        }

        $serverId = (int) (($provision->wizard_payload['server_id'] ?? 0) ?: 0);
        $server = $serverId ? PlatformServer::query()->find($serverId) : null;

        $useRemote = $server && ! $this->isLocalhostServer($server);
        if (! $useRemote) {
            $server = $this->ensureLocalhostServer();
        }

        if (! $server) {
            return $this->fail($provision, 'platform.no_ready_server');
        }

        $siteType = $provision->wizard_payload['site_type_slug']
            ?? $provision->package?->businessType?->slug
            ?? 'corporate';

        try {
            ProvisionProgress::assertNotCancelled($provision);
            if ($useRemote) {
                $this->remote->provisionFromSiteBuilder($provision, $server, $siteType);
            } else {
                $this->local->provisionFromSiteBuilder($provision, $server, $siteType);
            }
            $this->audit->log($provision->created_by, 'provision.launched', $provision->fresh());
        } catch (Throwable $e) {
            if (str_contains($e->getMessage(), 'platform.provision_cancelled')) {
                ProvisionProgress::report($provision, ProvisionProgress::PHASE_CANCELLED);

                return $provision->fresh(['license', 'package', 'crmAccount']);
            }

            return $this->fail($provision, $e->getMessage());
        }

        return $provision->fresh(['license', 'package', 'crmAccount']);
    }

    /**
     * @return array{exit_code:int,stdout:string,stderr:string}
     */
    public function start(WebinoSiteProvision $provision): array
    {
        if ($this->shouldUseLocal($provision)) {
            return $this->local->start($provision);
        }

        return $this->remote->start($provision);
    }

    /**
     * @return array{exit_code:int,stdout:string,stderr:string}
     */
    public function stop(WebinoSiteProvision $provision): array
    {
        if ($this->shouldUseLocal($provision)) {
            return $this->local->stop($provision);
        }

        return $this->remote->stop($provision);
    }

    public function logs(WebinoSiteProvision $provision, int $tail = 200): string
    {
        if ($this->shouldUseLocal($provision)) {
            return $this->local->logs($provision, $tail);
        }

        return $this->remote->logs($provision, $tail);
    }

    /**
     * @param  'frontend'|'backend'|'migrate'|'full'  $target
     * @return array{exit_code:int,stdout:string,stderr:string,log?:string}
     */
    public function runUpdate(WebinoSiteProvision $provision, string $target): array
    {
        if (! $this->shouldUseLocal($provision)) {
            throw new \RuntimeException('platform.remote_update_not_supported');
        }

        $payload = $provision->wizard_payload ?? [];
        $payload['update'] = [
            'target' => $target,
            'status' => 'running',
            'started_at' => now()->toIso8601String(),
        ];
        $provision->update(['wizard_payload' => $payload]);

        $result = match ($target) {
            'frontend' => $this->local->updateFrontend($provision),
            'backend' => $this->local->updateBackend($provision),
            'migrate' => $this->local->migrate($provision),
            'full' => $this->local->updateApp($provision),
            default => throw new \InvalidArgumentException('Invalid update target.'),
        };

        $payload = $provision->fresh()->wizard_payload ?? [];
        $payload['update'] = [
            'target' => $target,
            'status' => ($result['exit_code'] ?? 1) === 0 ? 'done' : 'failed',
            'log' => substr((string) ($result['log'] ?? ''), 0, 8000),
            'finished_at' => now()->toIso8601String(),
        ];
        $provision->update(['wizard_payload' => $payload]);
        $this->audit->log($provision->created_by, 'provision.update_'.$target, $provision);

        return $result;
    }

    public function changeDomain(WebinoSiteProvision $provision, string $newDomain): void
    {
        if ($this->shouldUseLocal($provision)) {
            $this->local->changeDomain($provision, $newDomain);
        } else {
            throw new \RuntimeException('platform.remote_domain_change_not_supported');
        }
    }

    /**
     * Reload Caddy / optionally re-issue cert for one domain. Never deletes caddy_data volume.
     *
     * @return array{ok:bool,ssl_status:?string,expires_at:?string,forced:bool,log?:string}
     */
    public function renewSsl(WebinoSiteProvision $provision, bool $force = false): array
    {
        if (! $this->shouldUseLocal($provision)) {
            throw new \RuntimeException('platform.remote_ssl_renew_not_supported');
        }

        $result = $this->local->renewSsl($provision, $force);

        $payload = $provision->wizard_payload ?? [];
        $payload['ssl'] = [
            'ssl_status' => $result['ssl_status'] ?? null,
            'expires_at' => $result['expires_at'] ?? null,
            'log' => $result['log'] ?? null,
            'forced' => $force,
            'updated_at' => now()->toIso8601String(),
        ];
        $updates = ['wizard_payload' => $payload];

        if (($result['ssl_status'] ?? '') === 'active'
            && $provision->status === WebinoSiteProvision::STATUS_SSL_PENDING) {
            $updates['status'] = WebinoSiteProvision::STATUS_READY;
            $updates['ready_at'] = $provision->ready_at ?? now();
            $updates['error_log'] = null;
        }

        $provision->update($updates);

        $this->audit->log($provision->created_by, 'provision.ssl_renew', $provision, [
            'force' => $force,
            'ok' => $result['ok'] ?? false,
            'ssl_status' => $result['ssl_status'] ?? null,
        ]);

        return $result;
    }

    /**
     * @return array{ssl_status:?string,expires_at:?string,domain:?string}
     */
    public function sslInfo(WebinoSiteProvision $provision): array
    {
        if ($this->shouldUseLocal($provision)) {
            return $this->local->sslInfo($provision);
        }

        return [
            'ssl_status' => null,
            'expires_at' => null,
            'domain' => $provision->domain,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function callTenantApi(WebinoSiteProvision $provision, string $path, array $payload = []): array
    {
        if ($this->shouldUseLocal($provision)) {
            return $this->local->callTenantApi($provision, $path, $payload);
        }

        throw new \RuntimeException('platform.remote_tenant_api_not_supported');
    }

    public function poll(WebinoSiteProvision $provision): WebinoSiteProvision
    {
        if ($provision->status === WebinoSiteProvision::STATUS_SSL_PENDING) {
            if ($this->waitForHealthy($provision->domain, attempts: 1)) {
                $payload = $provision->wizard_payload ?? [];
                $seed = $this->buildSeedJson($provision, $payload);
                try {
                    $this->bootstrapRemoteSite($provision->domain, (string) $provision->provision_token, $seed);
                    $provision->update([
                        'status' => WebinoSiteProvision::STATUS_READY,
                        'ready_at' => now(),
                    ]);
                } catch (Throwable $e) {
                    $provision->update(['error_log' => $e->getMessage()]);
                }
            }
        }

        return $provision->fresh(['license', 'package', 'crmAccount']);
    }

    public function cancel(WebinoSiteProvision $provision): WebinoSiteProvision
    {
        if (! in_array($provision->status, [
            WebinoSiteProvision::STATUS_PENDING,
            WebinoSiteProvision::STATUS_PROVISIONING,
            WebinoSiteProvision::STATUS_SSL_PENDING,
        ], true)) {
            throw new \InvalidArgumentException('Only in-progress provisions can be cancelled.');
        }

        $provision->update([
            'status' => WebinoSiteProvision::STATUS_CANCELLED,
            'progress' => ProvisionProgress::make(ProvisionProgress::PHASE_CANCELLED),
            'error_log' => trim(($provision->error_log ?? '')."\nCancelled by user."),
        ]);

        try {
            if ($this->shouldUseLocal($provision)) {
                $this->local->destroyStack($provision);
            }
        } catch (Throwable $e) {
            report($e);
        }

        $this->audit->log($provision->created_by, 'provision.cancelled', $provision);

        return $provision->fresh(['license', 'package', 'crmAccount']);
    }

    public function rollback(WebinoSiteProvision $provision): WebinoSiteProvision
    {
        try {
            if ($this->shouldUseLocal($provision)) {
                $this->local->destroyStack($provision);
            }
        } catch (Throwable $e) {
            report($e);
        }

        if ($provision->license) {
            $this->licenses->revoke($provision->license);
        }

        $provision->update([
            'status' => WebinoSiteProvision::STATUS_FAILED,
            'progress' => ProvisionProgress::make(ProvisionProgress::PHASE_FAILED),
            'error_log' => trim(($provision->error_log ?? '')."\nRolled back."),
        ]);

        return $provision->fresh();
    }

    protected function shouldUseLocal(WebinoSiteProvision $provision): bool
    {
        $serverId = (int) (($provision->wizard_payload['server_id'] ?? 0) ?: 0);
        if (! $serverId) {
            return true;
        }
        $server = PlatformServer::query()->find($serverId);

        return ! $server || $this->isLocalhostServer($server);
    }

    protected function isLocalhostServer(PlatformServer $server): bool
    {
        return (bool) $server->is_localhost
            || in_array($server->ip, ['127.0.0.1', 'localhost', '::1'], true)
            || strcasecmp((string) $server->name, 'localhost') === 0;
    }

    protected function ensureLocalhostServer(): PlatformServer
    {
        $settings = CoreHostingSetting::current();
        $dirty = false;
        if (! filled($settings->platform_base_domain)) {
            $settings->platform_base_domain = 'webinaagency.ir';
            $dirty = true;
        }
        if (! filled($settings->provision_webhook_secret)) {
            $settings->provision_webhook_secret = bin2hex(random_bytes(32));
            $dirty = true;
        }
        if ($dirty) {
            $settings->save();
        }

        return PlatformServer::query()->firstOrCreate(
            ['name' => 'localhost'],
            [
                'ip' => '127.0.0.1',
                'port' => 22,
                'user' => 'root',
                'status' => 'ready',
                'is_localhost' => true,
                'proxy_type' => 'caddy',
                'meta' => ['managed_by' => 'site_builder'],
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function buildSeedJson(WebinoSiteProvision $provision, array $payload): array
    {
        $package = $provision->package;
        $type = $package?->businessType;
        $category = $type?->category;
        $siteType = $payload['site_type_slug'] ?? $type?->slug ?? 'corporate';

        return [
            'tenant_name' => $payload['site_name'] ?? $provision->slug,
            'store_display_name' => $payload['site_name'] ?? $provision->slug,
            'default_currency' => $payload['currency'] ?? 'IRR',
            'domain' => $provision->domain,
            'license_key' => $provision->license?->license_key,
            'business_category_slug' => $category?->slug,
            'business_type_slug' => $type?->slug,
            'site_type_slug' => $siteType,
            'vertical' => $type?->slug,
            'package_sku' => $package?->sku,
            'theme_preset' => $type?->theme_preset,
            'nav_preset' => $type?->nav_preset,
            'branding' => [
                'logo_url' => $payload['logo_url'] ?? null,
                'description' => $payload['description'] ?? null,
            ],
            'admin_email' => $payload['admin_email'] ?? null,
            'admin_name' => $payload['admin_name'] ?? 'Admin',
            'crm_account_id' => $provision->crm_account_id,
        ];
    }

    protected function waitForHealthy(string $domain, int $attempts = 12): bool
    {
        $url = 'https://'.$domain.'/up';
        for ($i = 0; $i < $attempts; $i++) {
            try {
                $res = Http::timeout(8)->get($url);
                if ($res->successful()) {
                    return true;
                }
            } catch (Throwable) {
            }
            sleep(5);
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $seed
     */
    protected function bootstrapRemoteSite(string $domain, string $token, array $seed): void
    {
        $settings = CoreHostingSetting::current();
        $secret = (string) ($settings->provision_webhook_secret ?? '');
        if ($secret === '') {
            throw new \RuntimeException('platform.provision_hmac_missing');
        }
        $body = json_encode(['seed' => $seed], JSON_UNESCAPED_UNICODE);
        Http::withHeaders([
            'X-Provision-Token' => $token,
            'X-Provision-Signature' => hash_hmac('sha256', $body, $secret),
        ])
            ->withBody($body, 'application/json')
            ->timeout(60)
            ->post('https://'.$domain.'/api/v1/provision/bootstrap')
            ->throw();
    }

    protected function fail(WebinoSiteProvision $provision, string $message): WebinoSiteProvision
    {
        $provision->update([
            'status' => WebinoSiteProvision::STATUS_FAILED,
            'progress' => ProvisionProgress::make(ProvisionProgress::PHASE_FAILED),
            'error_log' => $message,
        ]);
        $this->audit->log($provision->created_by, 'provision.failed', $provision, ['error' => $message]);

        return $provision->fresh();
    }
}
