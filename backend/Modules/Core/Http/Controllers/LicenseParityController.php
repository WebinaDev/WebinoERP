<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Modules\Core\Entities\CoreLicense;
use Modules\Core\Services\CoreLicenseMetaNormalizer;
use Modules\Core\Services\CoreLicenseResolver;
use Modules\Platform\Support\SiteTypeProfiles;
use Modules\SiteBuilder\Entities\WebinoPackage;
use Modules\SiteBuilder\Services\LicenseProvisionerService;

class LicenseParityController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => CoreLicense::query()->orderByDesc('id')->get()]);
    }

    public function store(Request $request, LicenseProvisionerService $licenses): JsonResponse
    {
        $data = $request->validate([
            'project_name' => 'required|string|max:255',
            'domain' => 'required|string|max:255',
            'product' => 'nullable|string|max:64',
            'logo_url' => 'nullable|string|max:1000',
            'status' => 'nullable|string|max:50|in:active,inactive,cancelled,revoked',
            'start_date' => 'nullable|date',
            'expires_at' => 'nullable|date',
            'max_users' => 'nullable|integer|min:0',
            'site_type' => 'nullable|string|max:64',
            'package_id' => 'nullable|integer|min:1',
            'meta' => 'nullable|array',
        ]);

        $domain = CoreLicenseResolver::normalizeDomain($data['domain']);
        $product = CoreLicenseResolver::normalizeProduct($data['product'] ?? 'webinodashboard');
        if ($domain === '' || ! $this->isValidDomain($domain)) {
            return response()->json(['message' => 'Invalid domain'], 422);
        }

        $existsQ = CoreLicense::query()->where('domain', $domain);
        if (Schema::hasColumn('core_licenses', 'product')) {
            $existsQ->where('product', $product);
        }
        if ($existsQ->exists()) {
            return response()->json(['message' => 'Domain already licensed for this product'], 422);
        }

        $key = CoreLicenseResolver::internalKeyFor($domain, $product);

        if (! empty($data['package_id'])) {
            $package = WebinoPackage::query()->with(['businessType.category', 'features'])->find((int) $data['package_id']);
            if ($package) {
                $created = $licenses->createForProvision(
                    $domain,
                    $package,
                    [
                        'site_type' => $data['site_type'] ?? null,
                        'project_name' => $data['project_name'],
                        'logo_url' => $data['logo_url'] ?? null,
                        'start_date' => $data['start_date'] ?? now()->toDateString(),
                        'expires_at' => $data['expires_at'] ?? null,
                        'max_users' => $data['max_users'] ?? null,
                        'product' => $product,
                    ],
                    $request->user()?->id,
                );
                $updates = [
                    'project_name' => $data['project_name'],
                    'logo_url' => $data['logo_url'] ?? null,
                    'start_date' => $data['start_date'] ?? now()->toDateString(),
                    'status' => $data['status'] ?? 'active',
                    'product' => $product,
                    'license_key' => $key,
                ];
                $created->update(CoreLicense::attributesForSchema($updates));
                CoreLicenseResolver::forgetCheckCache($created->domain, $created->product ?? $product);
                CoreLicenseMetaNormalizer::forgetCheckCache($created->domain, null);

                return response()->json(['data' => $created->fresh()], 201);
            }
        }

        $meta = [];
        $siteType = (string) ($data['site_type'] ?? '');
        if ($siteType !== '' && SiteTypeProfiles::isValid($siteType)) {
            $meta = CoreLicenseMetaNormalizer::validateForStorage([
                'modules' => SiteTypeProfiles::moduleSlugsFor($siteType),
                'vertical' => $siteType,
                'site_type' => $siteType,
                'theme_preset' => SiteTypeProfiles::all()[$siteType]['theme'] ?? null,
                'module_matrix' => SiteTypeProfiles::modulesFor($siteType),
            ]) ?? [];
        } elseif (isset($data['meta'])) {
            try {
                $meta = CoreLicenseMetaNormalizer::validateForStorage($data['meta']) ?? [];
            } catch (InvalidArgumentException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        }

        $license = CoreLicense::createForSchema([
            'license_key' => $key,
            'project_name' => $data['project_name'],
            'domain' => $domain,
            'product' => $product,
            'logo_url' => $data['logo_url'] ?? null,
            'status' => $data['status'] ?? 'active',
            'start_date' => $data['start_date'] ?? now()->toDateString(),
            'expires_at' => $data['expires_at'] ?? null,
            'max_users' => $data['max_users'] ?? null,
            'meta' => count($meta) > 0 ? $meta : null,
            'created_by' => $request->user()?->id,
        ]);

        CoreLicenseResolver::forgetCheckCache($license->domain, $license->product ?? $product);
        CoreLicenseMetaNormalizer::forgetCheckCache($license->domain, null);

        return response()->json(['data' => $license], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $license = CoreLicense::query()->findOrFail($id);
        $data = $request->validate([
            'project_name' => 'sometimes|string|max:255',
            'domain' => 'sometimes|string|max:255',
            'product' => 'sometimes|nullable|string|max:64',
            'logo_url' => 'nullable|string|max:1000',
            'status' => 'sometimes|string|max:50',
            'start_date' => 'nullable|date',
            'expires_at' => 'nullable|date',
            'max_users' => 'nullable|integer|min:0',
            'meta' => 'sometimes|nullable|array',
            'replace_meta' => 'sometimes|boolean',
        ]);

        if (array_key_exists('domain', $data)) {
            $domain = CoreLicenseResolver::normalizeDomain((string) $data['domain']);
            if ($domain === '' || ! $this->isValidDomain($domain)) {
                return response()->json(['message' => 'Invalid domain'], 422);
            }
            $product = CoreLicenseResolver::normalizeProduct(
                $data['product'] ?? $license->product ?? 'webino'
            );
            $dup = CoreLicense::query()->where('domain', $domain)->where('id', '!=', $license->id);
            if (Schema::hasColumn('core_licenses', 'product')) {
                $dup->where('product', $product);
            }
            if ($dup->exists()) {
                return response()->json(['message' => 'Domain already licensed for this product'], 422);
            }
            $data['domain'] = $domain;
            $data['license_key'] = CoreLicenseResolver::internalKeyFor($domain, $product);
        }

        if (array_key_exists('product', $data)) {
            $data['product'] = CoreLicenseResolver::normalizeProduct($data['product']);
            $data['license_key'] = CoreLicenseResolver::internalKeyFor(
                (string) ($data['domain'] ?? $license->domain),
                $data['product']
            );
        }

        $replaceMeta = (bool) ($data['replace_meta'] ?? false);
        unset($data['replace_meta']);

        if (array_key_exists('meta', $data)) {
            $incoming = $data['meta'];
            unset($data['meta']);
            try {
                $sanitized = CoreLicenseMetaNormalizer::validateForStorage($incoming);
            } catch (InvalidArgumentException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
            if ($replaceMeta) {
                $data['meta'] = is_array($sanitized) && count($sanitized) > 0 ? $sanitized : null;
            } else {
                $merged = array_merge(is_array($license->meta) ? $license->meta : [], is_array($sanitized) ? $sanitized : []);
                $data['meta'] = count($merged) > 0 ? $merged : null;
            }
        }

        $license->update(CoreLicense::attributesForSchema($data));

        CoreLicenseResolver::forgetCheckCache($license->domain, $license->product ?? null);
        CoreLicenseMetaNormalizer::forgetCheckCache($license->domain, null);

        return response()->json(['data' => $license->fresh()]);
    }

    public function renew(Request $request, int $id): JsonResponse
    {
        $license = CoreLicense::query()->findOrFail($id);
        $data = $request->validate([
            'expires_at' => 'nullable|date',
        ]);
        $license->update([
            'expires_at' => $data['expires_at'] ?? now()->addYear(),
            'status' => 'active',
        ]);

        CoreLicenseResolver::forgetCheckCache($license->domain, $license->product ?? null);
        CoreLicenseMetaNormalizer::forgetCheckCache($license->domain, null);

        return response()->json(['data' => $license]);
    }

    public function cancel(int $id): JsonResponse
    {
        $license = CoreLicense::query()->findOrFail($id);
        $license->update(['status' => 'cancelled']);

        CoreLicenseResolver::forgetCheckCache($license->domain, $license->product ?? null);
        CoreLicenseMetaNormalizer::forgetCheckCache($license->domain, null);

        return response()->json(['data' => $license]);
    }

    public function destroy(int $id): JsonResponse
    {
        $license = CoreLicense::query()->findOrFail($id);
        $domain = $license->domain;
        $product = $license->product ?? null;
        $license->delete();

        CoreLicenseResolver::forgetCheckCache($domain, $product);
        CoreLicenseMetaNormalizer::forgetCheckCache($domain, null);

        return response()->json([], 204);
    }

    protected function isValidDomain(string $domain): bool
    {
        if (strlen($domain) > 253) {
            return false;
        }

        return (bool) preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', $domain)
            || (bool) preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.local$/i', $domain)
            || filter_var($domain, FILTER_VALIDATE_IP) !== false;
    }
}
