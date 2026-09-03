<?php

namespace Modules\SiteBuilder\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\Core\Entities\CoreHostingSetting;
use Modules\Platform\Entities\PlatformServer;
use Modules\Platform\Services\WebinoDashboardProvisioner;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;
use Throwable;

class SiteProvisionOrchestrator
{
    public function __construct(
        private readonly WebinoDashboardProvisioner $platform,
        private readonly LicenseProvisionerService $licenses,
        private readonly SiteProvisionAuditLogger $audit,
    ) {}

    public function launch(WebinoSiteProvision $provision): WebinoSiteProvision
    {
        $provision->load(['package.businessType.category', 'package.features', 'crmAccount']);

        $serverId = (int) (($provision->wizard_payload['server_id'] ?? 0) ?: 0);
        $server = $serverId
            ? PlatformServer::query()->find($serverId)
            : PlatformServer::query()->where('status', 'ready')->orderBy('id')->first();

        if (! $server) {
            return $this->fail($provision, 'platform.no_ready_server');
        }

        $siteType = $provision->wizard_payload['site_type_slug']
            ?? $provision->package?->businessType?->slug
            ?? 'corporate';

        try {
            $this->platform->provisionFromSiteBuilder($provision, $server, $siteType);
            $this->audit->log($provision->created_by, 'provision.launched', $provision->fresh());
        } catch (Throwable $e) {
            return $this->fail($provision, $e->getMessage());
        }

        return $provision->fresh(['license', 'package', 'crmAccount']);
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

    public function rollback(WebinoSiteProvision $provision): WebinoSiteProvision
    {
        if ($provision->license) {
            $this->licenses->revoke($provision->license);
        }

        $provision->update([
            'status' => WebinoSiteProvision::STATUS_FAILED,
            'error_log' => trim(($provision->error_log ?? '')."\nRolled back."),
        ]);

        return $provision->fresh();
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
            'error_log' => $message,
        ]);
        $this->audit->log($provision->created_by, 'provision.failed', $provision, ['error' => $message]);

        return $provision->fresh();
    }
}
