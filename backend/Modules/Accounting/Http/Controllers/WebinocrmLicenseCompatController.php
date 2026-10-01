<?php

namespace Modules\Accounting\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Http\Controllers\Concerns\VerifiesWebinocrmLicenseSignature;
use Modules\Core\Entities\CoreLicense;
use Modules\Core\Services\CoreLicenseMetaNormalizer;
use Modules\Core\Services\CoreLicenseResolver;

/**
 * Public REST parity with webinocrm / WP license-management APIs.
 * Routes: POST /api/webinocrm/v1/license/check|activate
 *
 * Entitlement identity: domain (+ product). license_key is ignored for lookup
 * (accepted for HMAC/WP compat only). HMAC is optional service auth:
 * domain|{product}|ts. check() allows unsigned domain-status (rate-limited).
 */
class WebinocrmLicenseCompatController extends Controller
{
    use VerifiesWebinocrmLicenseSignature;

    public function check(Request $request): JsonResponse
    {
        // Domain (+ product) entitlement. HMAC is optional service auth.
        // Unsigned checks are allowed (rate-limited); bad signatures still rejected.
        if (! $this->authorizeLicenseCheck($request)) {
            return response()->json(['error' => ['code' => 'INVALID_SIGNATURE', 'message' => 'Invalid signature']], 403);
        }

        $domain = CoreLicenseResolver::normalizeDomain(
            (string) ($request->input('domain') ?: $request->getHost())
        );
        $rateKey = 'license-domain-check:'.$request->ip().':'.$domain;
        if (RateLimiter::tooManyAttempts($rateKey, 60)) {
            return response()->json(['error' => ['code' => 'RATE_LIMITED', 'message' => 'Too many license checks']], 429);
        }
        RateLimiter::hit($rateKey, 60);
        // WP compat: if license_key looks like a hostname and domain empty, use it as domain.
        if ($domain === '' && $request->filled('license_key')) {
            $maybe = CoreLicenseResolver::normalizeDomain((string) $request->input('license_key'));
            if ($maybe !== '' && str_contains($maybe, '.')) {
                $domain = $maybe;
            }
        }
        $product = CoreLicenseResolver::normalizeProduct(
            $request->input('product', $request->input('product_slug'))
        );
        $cacheKey = CoreLicenseResolver::cacheKey($domain, $product);

        $payload = Cache::remember($cacheKey, 3600, function () use ($domain, $product) {
            $row = CoreLicenseResolver::find($domain, $product);

            if (! $row) {
                return [
                    'status' => 'invalid',
                    'valid' => false,
                    'legacy_status' => 'invalid',
                    'expiry_date' => null,
                    'remaining_days' => 0,
                    'remaining_percentage' => 0,
                    'demo' => false,
                    'active' => false,
                    'expired' => false,
                    'domain' => $domain,
                    'product' => $product,
                    'licensed_modules' => [],
                    'vertical' => null,
                    'sku' => null,
                    'module_git_repos' => [],
                    'modules' => [],
                    'entitlements' => [],
                ];
            }

            $exp = $row->expires_at;
            $remaining = $exp ? max(0, (int) now()->diffInDays($exp, false)) : 365;
            $maxUsers = (int) ($row->max_users ?? 0);
            $userCount = (int) DB::table('users')->count();
            $expired = $exp !== null && $exp->isPast();
            $metaRaw = is_array($row->meta) ? $row->meta : [];
            $isDemo = filter_var($metaRaw['demo'] ?? $metaRaw['is_demo'] ?? false, FILTER_VALIDATE_BOOLEAN)
                || str_starts_with(strtolower((string) ($row->license_key ?? '')), 'demo-')
                || (($metaRaw['sku'] ?? null) === 'demo');
            $activeRow = $row->status === 'active' && ! $expired && ($maxUsers <= 0 || $userCount <= $maxUsers);
            if ($activeRow && $isDemo) {
                $lifecycle = 'demo';
            } elseif ($activeRow) {
                $lifecycle = 'active';
            } elseif ($expired || $row->status === 'expired') {
                $lifecycle = 'expired';
            } else {
                $lifecycle = 'invalid';
            }
            $valid = in_array($lifecycle, ['active', 'demo'], true);

            $norm = CoreLicenseMetaNormalizer::normalize($row->meta);
            $repos = CoreLicenseMetaNormalizer::mergeModuleGitReposWithRegistry($norm['module_git_repos']);

            return [
                'status' => $lifecycle,
                'valid' => $valid,
                'legacy_status' => $valid ? 'valid' : 'invalid',
                'expiry_date' => $exp?->toDateString(),
                'remaining_days' => $remaining,
                'remaining_percentage' => $exp ? min(100, (int) round($remaining / max(1, max(1, $exp->diffInDays($row->created_at ?? now()))) * 100)) : 100,
                'demo' => $lifecycle === 'demo',
                'active' => $lifecycle === 'active' || $lifecycle === 'demo',
                'expired' => $lifecycle === 'expired',
                'domain' => $row->domain,
                'product' => $row->product ?? $product,
                'licensed_modules' => $norm['licensed_modules'],
                'vertical' => $norm['vertical'],
                'sku' => $norm['sku'],
                'business_category' => $norm['business_category'],
                'business_type' => $norm['business_type'],
                'site_type' => $norm['site_type'] ?? $norm['business_type'],
                'features' => $norm['features'],
                'theme_preset' => $norm['theme_preset'],
                'nav_preset' => $norm['nav_preset'],
                'module_git_repos' => $repos,
                'modules' => $norm['licensed_modules'],
                'entitlements' => $norm['licensed_modules'],
            ];
        });

        return response()->json(['data' => $payload]);
    }

    public function activate(Request $request): JsonResponse
    {
        if (! $this->verifyLicenseRequest($request)) {
            return response()->json(['error' => ['code' => 'INVALID_SIGNATURE', 'message' => 'Invalid signature']], 403);
        }

        $data = $request->validate([
            'domain' => 'required|string|max:255',
            // Deprecated: accepted for WP clients; ignored for identity (domain+product win).
            'license_key' => 'nullable|string|max:255',
            'product' => 'nullable|string|max:64',
            'product_slug' => 'nullable|string|max:64',
            'expires_at' => 'nullable|date',
            'max_users' => 'nullable|integer|min:0',
            'meta' => 'nullable|array',
        ]);

        $domain = CoreLicenseResolver::normalizeDomain($data['domain']);
        $product = CoreLicenseResolver::normalizeProduct($data['product'] ?? $data['product_slug'] ?? null);

        try {
            $metaIn = isset($data['meta']) ? CoreLicenseMetaNormalizer::validateForStorage($data['meta']) : null;
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'error' => ['code' => 'INVALID_META', 'message' => $e->getMessage()],
            ], 422);
        }
        $meta = array_merge(
            ['activated_via' => 'api'],
            is_array($metaIn) ? $metaIn : []
        );

        $internalKey = CoreLicenseResolver::internalKeyFor($domain, $product);
        $attrs = CoreLicense::attributesForSchema([
            'status' => 'active',
            'expires_at' => $data['expires_at'] ?? null,
            'max_users' => $data['max_users'] ?? 0,
            'meta' => $meta,
            'created_by' => null,
            'license_key' => $internalKey,
            'product' => $product,
        ]);

        $match = ['domain' => $domain];
        if (\Illuminate\Support\Facades\Schema::hasColumn('core_licenses', 'product')) {
            $match['product'] = $product;
        }

        $row = CoreLicense::query()->updateOrCreate(
            CoreLicense::attributesForSchema($match),
            $attrs
        );

        CoreLicenseResolver::forgetCheckCache($domain, $product);
        CoreLicenseMetaNormalizer::forgetCheckCache($domain, null);

        return response()->json([
            'data' => [
                'status' => 'ok',
                'message' => 'License stored',
                'license_id' => $row->id,
                'domain' => $domain,
                'product' => $product,
            ],
        ]);
    }
}
