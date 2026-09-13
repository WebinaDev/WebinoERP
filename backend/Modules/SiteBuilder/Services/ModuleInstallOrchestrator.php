<?php

namespace Modules\SiteBuilder\Services;

use Modules\Core\Entities\CoreLicense;
use Modules\Core\Entities\ModuleGitSource;
use Modules\Core\Services\CoreLicenseMetaNormalizer;
use Modules\Core\Services\OrgGit\OrgGitProviderFactory;
use Modules\Marketplace\Entities\MarketplaceModule;
use Modules\Marketplace\Entities\SiteModuleInstall;
use Modules\Marketplace\Services\MarketplaceLicenseService;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;
use Modules\SiteBuilder\Jobs\InstallSiteModuleJob;
use RuntimeException;
use Throwable;

class ModuleInstallOrchestrator
{
    public function __construct(
        private readonly SiteProvisionOrchestrator $sites,
        private readonly MarketplaceLicenseService $licenses,
        private readonly OrgGitProviderFactory $gitFactory,
    ) {}

    /**
     * Queue or run install of a module onto a customer site.
     *
     * @return array{install: SiteModuleInstall, queued: bool}
     */
    public function install(WebinoSiteProvision $site, string $moduleSlug, bool $async = true): array
    {
        if (! $site->license_id) {
            throw new RuntimeException('Site has no license. Purchase or prepare a license first.');
        }

        $this->ensureEntitled($site, $moduleSlug);
        $this->ensureGitSource($moduleSlug);

        $row = SiteModuleInstall::query()->updateOrCreate(
            [
                'site_provision_id' => $site->id,
                'module_slug' => $moduleSlug,
            ],
            [
                'status' => SiteModuleInstall::STATUS_PENDING,
                'error_message' => null,
                'started_at' => now(),
                'finished_at' => null,
                'meta' => ['requested_at' => now()->toIso8601String()],
            ]
        );

        if ($async) {
            InstallSiteModuleJob::dispatch($site->id, $moduleSlug);

            return ['install' => $row, 'queued' => true];
        }

        $this->run($site, $moduleSlug);

        return ['install' => $row->fresh(), 'queued' => false];
    }

    public function run(WebinoSiteProvision $site, string $moduleSlug): SiteModuleInstall
    {
        $row = SiteModuleInstall::query()->firstOrCreate(
            [
                'site_provision_id' => $site->id,
                'module_slug' => $moduleSlug,
            ],
            ['status' => SiteModuleInstall::STATUS_PENDING]
        );

        try {
            $this->ensureEntitled($site, $moduleSlug);
            $this->ensureGitSource($moduleSlug);

            $row->update([
                'status' => SiteModuleInstall::STATUS_CLONING,
                'started_at' => $row->started_at ?? now(),
                'error_message' => null,
            ]);

            // Ensure license meta includes slug + repo, then sync + install on tenant.
            $this->licenses->grantModuleToSite($site, $moduleSlug);

            $this->sites->callTenantApi($site, 'provision/modules/install', [
                'slug' => $moduleSlug,
            ]);

            $row->update(['status' => SiteModuleInstall::STATUS_MIGRATING]);

            try {
                $this->sites->callTenantApi($site, 'provision/license-sync', []);
            } catch (Throwable $e) {
                report($e);
            }

            $status = [];
            try {
                $status = $this->sites->callTenantApi($site, 'provision/modules/'.$moduleSlug.'/status', []);
            } catch (Throwable $e) {
                report($e);
            }

            $payload = is_array($status['data'] ?? null) ? $status['data'] : $status;
            $ready = (bool) ($payload['installed'] ?? true);

            $row->update([
                'status' => $ready ? SiteModuleInstall::STATUS_READY : SiteModuleInstall::STATUS_FAILED,
                'version' => isset($payload['version']) && is_string($payload['version']) ? $payload['version'] : null,
                'meta' => ['tenant_status' => $payload],
                'finished_at' => now(),
                'error_message' => $ready ? null : 'Tenant reported module not installed',
            ]);
        } catch (Throwable $e) {
            $row->update([
                'status' => SiteModuleInstall::STATUS_FAILED,
                'error_message' => $e->getMessage(),
                'finished_at' => now(),
            ]);
            throw $e;
        }

        return $row->fresh();
    }

    public function status(WebinoSiteProvision $site, string $moduleSlug): array
    {
        $row = SiteModuleInstall::query()
            ->where('site_provision_id', $site->id)
            ->where('module_slug', $moduleSlug)
            ->first();

        $tenant = null;
        try {
            $tenant = $this->sites->callTenantApi($site, 'provision/modules/'.$moduleSlug.'/status', []);
        } catch (Throwable) {
            // site may be offline
        }

        return [
            'install' => $row,
            'tenant' => $tenant['data'] ?? $tenant,
        ];
    }

    protected function ensureEntitled(WebinoSiteProvision $site, string $moduleSlug): void
    {
        /** @var CoreLicense $license */
        $license = CoreLicense::query()->findOrFail($site->license_id);
        $norm = CoreLicenseMetaNormalizer::normalize($license->meta);
        if (in_array($moduleSlug, $norm['licensed_modules'], true)) {
            return;
        }

        // Auto-grant only for free marketplace modules; otherwise require purchase.
        $module = MarketplaceModule::query()->where('slug', $moduleSlug)->first();
        if ($module && (float) $module->price <= 0) {
            $this->licenses->grantModuleToSite($site, $moduleSlug);

            return;
        }

        throw new RuntimeException(
            'Module not licensed for this site. Purchase via Marketplace first: '.$moduleSlug
        );
    }

    protected function ensureGitSource(string $moduleSlug): void
    {
        $source = ModuleGitSource::query()->where('slug', $moduleSlug)->first();
        $marketplace = MarketplaceModule::query()->with(['repo', 'gitSource'])->where('slug', $moduleSlug)->first();

        if (! $source && $marketplace?->gitSource) {
            $source = $marketplace->gitSource;
        }

        $url = $source?->clone_url ?: $marketplace?->repo?->repo_url;
        if (! is_string($url) || $url === '') {
            throw new RuntimeException('No ModuleGitSource / marketplace repo for '.$moduleSlug);
        }

        // Best-effort provider health (non-fatal if token missing in local/dev).
        try {
            $provider = $this->gitFactory->make($marketplace?->repo?->provider);
            $provider->testConnection();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
