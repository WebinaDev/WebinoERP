<?php

namespace Modules\SiteBuilder\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Entities\CoreLicense;
use Modules\Core\Services\CoreLicenseMetaNormalizer;
use Modules\Core\Services\CoreLicenseResolver;
use Modules\Platform\Support\SiteTypeProfiles;
use Modules\SiteBuilder\Entities\WebinoPackage;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;
use RuntimeException;

class LicenseProvisionerService
{
    public const PRODUCT = 'webinodashboard';

    /**
     * Create a domain license or reuse the existing row for the same domain + product.
     * Conflicts (revoked, different product, different CRM customer) throw RuntimeException
     * whose message is a stable `platform.license_*` code.
     */
    public function attachForProvision(WebinoSiteProvision $provision, ?int $createdBy = null): CoreLicense
    {
        $provision->loadMissing(['package.businessType.category', 'package.features', 'license']);

        if (! $provision->package) {
            throw new RuntimeException('Package is required.');
        }

        $product = self::PRODUCT;
        $domain = CoreLicenseResolver::normalizeDomain((string) $provision->domain);
        if ($domain === '' || ! str_contains($domain, '.')) {
            throw new RuntimeException('platform.invalid_domain');
        }

        if ($provision->license_id && $provision->license) {
            $this->assertReusable($provision->license, $provision, $product);

            return $provision->license;
        }

        $existing = $this->findForDomain($domain, $product);
        if ($existing) {
            $this->assertReusable($existing, $provision, $product);

            return $existing;
        }

        $other = $this->findForDomain($domain, null);
        if ($other) {
            $otherProduct = CoreLicenseResolver::normalizeProduct((string) ($other->product ?? 'webino'));
            if ($otherProduct !== $product) {
                throw new RuntimeException('platform.license_wrong_product');
            }
            $this->assertReusable($other, $provision, $product);

            return $other;
        }

        $payload = $provision->wizard_payload ?? [];
        $siteType = (string) ($payload['site_type_slug'] ?? $provision->package->businessType?->slug ?? 'corporate');

        try {
            return $this->createForProvision(
                $domain,
                $provision->package,
                [
                    'selected_feature_slugs' => $payload['selected_feature_slugs'] ?? [],
                    'site_type' => $siteType,
                    'site_type_slug' => $siteType,
                    'site_name' => $payload['site_name'] ?? $provision->slug,
                    'project_name' => $payload['site_name'] ?? $provision->slug,
                    'product' => $product,
                ],
                $createdBy ?? $provision->created_by,
            );
        } catch (QueryException $e) {
            if (! $this->isUniqueViolation($e)) {
                throw $e;
            }
            $again = $this->findForDomain($domain, $product) ?? $this->findForDomain($domain, null);
            if (! $again) {
                throw $e;
            }
            $this->assertReusable($again, $provision, $product);

            return $again;
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function createForProvision(
        string $domain,
        WebinoPackage $package,
        array $context,
        ?int $createdBy = null,
    ): CoreLicense {
        $package->load(['businessType.category', 'features']);

        $type = $package->businessType;
        $category = $type?->category;

        $siteType = (string) ($context['site_type'] ?? $context['site_type_slug'] ?? $type?->slug ?? 'corporate');
        if (! SiteTypeProfiles::isValid($siteType)) {
            $siteType = 'corporate';
        }

        $profileModules = SiteTypeProfiles::moduleSlugsFor($siteType);
        $modules = array_values(array_unique(array_filter(array_merge(
            $profileModules,
            $context['extra_module_slugs'] ?? [],
        ))));

        $features = array_values(array_unique(array_merge(
            $package->features->pluck('slug')->all(),
            $context['selected_feature_slugs'] ?? [],
        )));

        $meta = CoreLicenseMetaNormalizer::validateForStorage([
            'modules' => $modules,
            'vertical' => $siteType,
            'site_type' => $siteType,
            'sku' => $package->sku,
            'business_category' => $category?->slug,
            'business_type' => $type?->slug,
            'features' => $features,
            'theme_preset' => SiteTypeProfiles::all()[$siteType]['theme'] ?? $type?->theme_preset,
            'nav_preset' => $type?->nav_preset,
            'module_matrix' => SiteTypeProfiles::modulesFor($siteType),
        ]) ?? [];

        $domain = CoreLicenseResolver::normalizeDomain($domain);
        $product = CoreLicenseResolver::normalizeProduct($context['product'] ?? 'webinodashboard');
        $licenseKey = CoreLicenseResolver::internalKeyFor($domain, $product);

        $projectName = (string) ($context['project_name'] ?? $context['site_name'] ?? $domain);
        if (! isset($meta['sku'])) {
            $meta['sku'] = $package->sku;
        }

        return CoreLicense::createForSchema([
            'license_key' => $licenseKey,
            'project_name' => $projectName,
            'domain' => $domain,
            'product' => $product,
            'logo_url' => $context['logo_url'] ?? null,
            'status' => 'active',
            'start_date' => $context['start_date'] ?? now()->toDateString(),
            'expires_at' => $context['expires_at'] ?? now()->addYear(),
            'max_users' => $context['max_users'] ?? null,
            'meta' => $meta,
            'created_by' => $createdBy,
        ]);
    }

    /**
     * @deprecated License identity is domain; kept for callers that still invoke key generation.
     */
    public function generateLicenseKey(): string
    {
        // Deterministic-looking placeholder is not used as entitlement; callers should prefer internalKeyFor(domain).
        return CoreLicenseResolver::internalKeyFor('pending.local', 'webino').'-'.substr(bin2hex(random_bytes(4)), 0, 8);
    }

    public function revoke(CoreLicense $license): void
    {
        $license->update(['status' => 'revoked']);
        CoreLicenseResolver::forgetCheckCache($license->domain, $license->product ?? null);
        CoreLicenseMetaNormalizer::forgetCheckCache($license->domain, null);
    }

    /**
     * Exact domain (+ optional product). Also matches a stored www. host.
     */
    protected function findForDomain(string $domain, ?string $product): ?CoreLicense
    {
        $domain = CoreLicenseResolver::normalizeDomain($domain);
        if ($domain === '') {
            return null;
        }

        $hosts = array_values(array_unique([$domain, 'www.'.$domain]));
        $query = CoreLicense::query()->whereIn('domain', $hosts);
        if ($product !== null && Schema::hasColumn('core_licenses', 'product')) {
            $query->where('product', CoreLicenseResolver::normalizeProduct($product));
        }

        return $query->orderByDesc('id')->first();
    }

    protected function assertReusable(CoreLicense $license, WebinoSiteProvision $provision, string $expectedProduct): void
    {
        $status = strtolower(trim((string) ($license->status ?? 'active')));
        if (in_array($status, ['revoked', 'cancelled'], true)) {
            throw new RuntimeException('platform.license_revoked');
        }

        if (Schema::hasColumn('core_licenses', 'product')) {
            $actual = CoreLicenseResolver::normalizeProduct((string) ($license->product ?? 'webino'));
            if ($actual !== $expectedProduct) {
                throw new RuntimeException('platform.license_wrong_product');
            }
        }

        $customerId = $provision->crm_account_id ? (int) $provision->crm_account_id : null;
        if (! $customerId) {
            return;
        }

        $conflict = WebinoSiteProvision::query()
            ->where('license_id', $license->id)
            ->where('id', '!=', $provision->id)
            ->whereNotNull('crm_account_id')
            ->where('crm_account_id', '!=', $customerId)
            ->exists();

        if ($conflict) {
            throw new RuntimeException('platform.license_customer_conflict');
        }
    }

    protected function isUniqueViolation(QueryException $e): bool
    {
        $state = (string) ($e->errorInfo[0] ?? $e->getCode());

        return in_array($state, ['23505', '23000'], true)
            || str_contains(strtolower($e->getMessage()), 'unique');
    }
}
