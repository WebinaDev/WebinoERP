<?php

namespace Modules\Marketplace\Services;

use Illuminate\Support\Facades\DB;
use Modules\Core\Entities\CoreLicense;
use Modules\Core\Entities\ModuleGitSource;
use Modules\Core\Services\CoreLicenseMetaNormalizer;
use Modules\Marketplace\Entities\MarketplaceEntitlement;
use Modules\Marketplace\Entities\MarketplaceModule;
use Modules\Marketplace\Entities\MarketplaceOrder;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;
use RuntimeException;

class MarketplaceLicenseService
{
    /**
     * After a paid marketplace order, grant entitled modules on the site license.
     */
    public function grantFromOrder(MarketplaceOrder $order): CoreLicense
    {
        return DB::transaction(function () use ($order) {
            $order->loadMissing('items');
            if ($order->items->isEmpty()) {
                throw new RuntimeException('Order has no items.');
            }

            $license = $this->resolveLicense($order);
            $meta = is_array($license->meta) ? $license->meta : [];
            $modules = $meta['modules'] ?? [];
            if (! is_array($modules)) {
                $modules = [];
            }
            $modules = array_values(array_unique(array_filter(array_map('strval', $modules))));

            $repos = $meta['module_repos'] ?? [];
            if (! is_array($repos)) {
                $repos = [];
            }

            foreach ($order->items as $item) {
                $slug = (string) $item->module_slug;
                if ($slug === '') {
                    continue;
                }
                if (! in_array($slug, $modules, true)) {
                    $modules[] = $slug;
                }

                $cloneUrl = $this->resolveCloneUrl($slug, $item->module_id);
                if ($cloneUrl) {
                    $repos = $this->upsertRepoEntry($repos, $slug, $cloneUrl);
                }

                if ($item->module_id) {
                    $module = MarketplaceModule::query()->find($item->module_id);
                    $this->upsertEntitlement(
                        (string) $license->domain,
                        (int) $item->module_id,
                        (int) $order->id,
                        (string) ($module?->version ?? '')
                    );
                }
            }

            $meta['modules'] = array_values(array_unique($modules));
            $meta['module_repos'] = array_values($repos);
            $license->meta = CoreLicenseMetaNormalizer::validateForStorage($meta) ?? $meta;
            $license->save();

            CoreLicenseMetaNormalizer::forgetCheckCache($license->domain, $license->license_key);

            $order->license_id = $license->id;
            if ($order->status === 'pending' || $order->status === 'paid') {
                $order->status = 'fulfilled';
            }
            $order->save();

            return $license->fresh();
        });
    }

    /**
     * Upsert marketplace entitlement row for a domain + module (CRM grant_entitlement parity).
     */
    public function upsertEntitlement(string $domain, int $moduleId, ?int $orderId = null, string $version = ''): MarketplaceEntitlement
    {
        $domain = strtolower(trim($domain));
        $row = MarketplaceEntitlement::query()->firstOrNew([
            'domain' => $domain,
            'module_id' => $moduleId,
        ]);
        $row->status = 'owned';
        if ($orderId) {
            $row->order_id = $orderId;
        }
        if ($version !== '') {
            $row->installed_version = $version;
        }
        $row->expires_at = null;
        $row->save();

        return $row->fresh();
    }

    /**
     * Manually issue/extend a single module entitlement for a site.
     */
    public function grantModuleToSite(WebinoSiteProvision $site, string $moduleSlug): CoreLicense
    {
        if (! $site->license_id) {
            throw new RuntimeException('Site has no license.');
        }

        /** @var CoreLicense $license */
        $license = CoreLicense::query()->findOrFail($site->license_id);
        $meta = is_array($license->meta) ? $license->meta : [];
        $modules = $meta['modules'] ?? [];
        if (! is_array($modules)) {
            $modules = [];
        }
        $modules = array_values(array_unique(array_filter(array_map('strval', $modules))));
        if (! in_array($moduleSlug, $modules, true)) {
            $modules[] = $moduleSlug;
        }

        $repos = $meta['module_repos'] ?? [];
        if (! is_array($repos)) {
            $repos = [];
        }
        $cloneUrl = $this->resolveCloneUrl($moduleSlug, null);
        if ($cloneUrl) {
            $repos = $this->upsertRepoEntry($repos, $moduleSlug, $cloneUrl);
        }

        $meta['modules'] = $modules;
        $meta['module_repos'] = array_values($repos);
        $license->meta = CoreLicenseMetaNormalizer::validateForStorage($meta) ?? $meta;
        $license->save();
        CoreLicenseMetaNormalizer::forgetCheckCache($license->domain, $license->license_key);

        return $license->fresh();
    }

    protected function resolveLicense(MarketplaceOrder $order): CoreLicense
    {
        if ($order->license_id) {
            return CoreLicense::query()->findOrFail($order->license_id);
        }

        if ($order->site_provision_id) {
            $site = WebinoSiteProvision::query()->findOrFail($order->site_provision_id);
            if ($site->license_id) {
                return CoreLicense::query()->findOrFail($site->license_id);
            }
            if (is_string($site->domain) && $site->domain !== '') {
                $byDomain = CoreLicense::query()->where('domain', $site->domain)->orderByDesc('id')->first();
                if ($byDomain) {
                    return $byDomain;
                }
            }
        }

        throw new RuntimeException('Cannot resolve license for order; set site_provision_id or license_id.');
    }

    protected function resolveCloneUrl(string $slug, ?int $moduleId): ?string
    {
        if ($moduleId) {
            $module = MarketplaceModule::query()->with(['repo', 'gitSource'])->find($moduleId);
            if ($module?->gitSource?->clone_url) {
                return $module->gitSource->clone_url;
            }
            if ($module?->repo?->repo_url) {
                return $module->repo->repo_url;
            }
        }

        $source = ModuleGitSource::query()->where('slug', $slug)->first();

        return $source?->clone_url;
    }

    /**
     * @param  list<mixed>  $repos
     * @return list<array{slug: string, repo_url: string}>
     */
    protected function upsertRepoEntry(array $repos, string $slug, string $cloneUrl): array
    {
        $out = [];
        $replaced = false;
        foreach ($repos as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $entrySlug = $entry['slug'] ?? $entry['module_slug'] ?? null;
            if ($entrySlug === $slug) {
                $out[] = ['slug' => $slug, 'repo_url' => $cloneUrl];
                $replaced = true;
            } else {
                $out[] = [
                    'slug' => (string) $entrySlug,
                    'repo_url' => (string) ($entry['repo_url'] ?? $entry['clone_url'] ?? ''),
                ];
            }
        }
        if (! $replaced) {
            $out[] = ['slug' => $slug, 'repo_url' => $cloneUrl];
        }

        return array_values(array_filter($out, fn ($e) => ($e['slug'] ?? '') !== '' && ($e['repo_url'] ?? '') !== ''));
    }
}
