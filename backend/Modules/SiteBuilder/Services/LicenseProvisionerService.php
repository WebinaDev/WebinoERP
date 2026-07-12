<?php

namespace Modules\SiteBuilder\Services;

use Illuminate\Support\Str;
use Modules\Core\Entities\CoreLicense;
use Modules\Core\Services\CoreLicenseMetaNormalizer;
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

        $modules = array_values(array_unique(array_filter(array_merge(
            $type?->default_module_slugs ?? [],
            $package->features->pluck('module_slug')->filter()->values()->all(),
            $context['extra_module_slugs'] ?? [],
        ))));

        $features = array_values(array_unique(array_merge(
            $package->features->pluck('slug')->all(),
            $context['selected_feature_slugs'] ?? [],
        )));

        $meta = CoreLicenseMetaNormalizer::validateForStorage([
            'modules' => $modules,
            'vertical' => $type?->slug,
            'sku' => $package->sku,
            'business_category' => $category?->slug,
            'business_type' => $type?->slug,
            'features' => $features,
            'theme_preset' => $type?->theme_preset,
            'nav_preset' => $type?->nav_preset,
        ]) ?? [];

        $licenseKey = $this->generateLicenseKey();

        return CoreLicense::query()->create([
            'license_key' => $licenseKey,
            'domain' => $domain,
            'status' => 'active',
            'expires_at' => $context['expires_at'] ?? now()->addYear(),
            'max_users' => $context['max_users'] ?? null,
            'meta' => $meta,
            'created_by' => $createdBy,
        ]);
    }

    public function generateLicenseKey(): string
    {
        do {
            $key = 'wb-'.Str::lower(Str::random(24));
        } while (CoreLicense::query()->where('license_key', $key)->exists());

        return $key;
    }

    public function revoke(CoreLicense $license): void
    {
        $license->update(['status' => 'revoked']);
        CoreLicenseMetaNormalizer::forgetCheckCache($license->domain, $license->license_key);
    }
}
