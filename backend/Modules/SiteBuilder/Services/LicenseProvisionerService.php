<?php

namespace Modules\SiteBuilder\Services;

use Modules\Core\Entities\CoreLicense;
use Modules\Core\Services\CoreLicenseMetaNormalizer;
use Modules\Core\Services\CoreLicenseResolver;
use Modules\Platform\Support\SiteTypeProfiles;
use Modules\SiteBuilder\Entities\WebinoPackage;

class LicenseProvisionerService
{
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
}
