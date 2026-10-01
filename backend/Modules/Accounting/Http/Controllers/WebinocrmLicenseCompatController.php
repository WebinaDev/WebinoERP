<?php

namespace Modules\Accounting\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Http\Controllers\Concerns\VerifiesWebinocrmLicenseSignature;
use Modules\Core\Entities\CoreLicense;
use Modules\Core\Services\CoreLicenseMetaNormalizer;

/**
 * Public REST parity with webinocrm includes/class-license-api.php
 * Routes: POST /api/webinocrm/v1/license/check|activate
 *
 * Body: domain, license_key, signature = HMAC_SHA256(secret, domain + "|" + license_key + "|" + ts), ts (unix)
 */
class WebinocrmLicenseCompatController extends Controller
{
    use VerifiesWebinocrmLicenseSignature;

    public function check(Request $request): JsonResponse
    {
        if (! $this->verifyLicenseRequest($request)) {
            return response()->json(['error' => ['code' => 'INVALID_SIGNATURE', 'message' => 'Invalid signature']], 403);
        }

        $domain = (string) $request->input('domain', $request->getHost());
        $cacheKey = 'license_check:'.md5($domain.'|'.$request->input('license_key'));

        $payload = Cache::remember($cacheKey, 3600, function () use ($domain, $request) {
            $row = CoreLicense::query()
                ->where('domain', $domain)
                ->when($request->filled('license_key'), fn ($q) => $q->where('license_key', $request->input('license_key')))
                ->orderByDesc('id')
                ->first();

            if (! $row) {
                return [
                    'status' => 'invalid',
                    'expiry_date' => null,
                    'remaining_days' => 0,
                    'remaining_percentage' => 0,
                    'licensed_modules' => [],
                    'vertical' => null,
                    'sku' => null,
                    'module_git_repos' => [],
                ];
            }

            $exp = $row->expires_at;
            $remaining = $exp ? max(0, (int) now()->diffInDays($exp, false)) : 365;
            $maxUsers = (int) ($row->max_users ?? 0);
            $userCount = (int) DB::table('users')->count();
            $expired = $exp !== null && $exp->isPast();
            $metaRaw = is_array($row->meta) ? $row->meta : [];
            $isDemo = filter_var($metaRaw['demo'] ?? $metaRaw['is_demo'] ?? false, FILTER_VALIDATE_BOOLEAN)
                || str_starts_with(strtolower((string) $row->license_key), 'demo-')
                || (($metaRaw['sku'] ?? null) === 'demo');
            $activeRow = $row->status === 'active' && ! $expired && ($maxUsers <= 0 || $userCount <= $maxUsers);
            // Lifecycle for Dashboard: active | expired | demo | invalid (keep valid boolean for older clients).
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
                // Backward-compat alias used by older Dashboard clients.
                'legacy_status' => $valid ? 'valid' : 'invalid',
                'expiry_date' => $exp?->toDateString(),
                'remaining_days' => $remaining,
                'remaining_percentage' => $exp ? min(100, (int) round($remaining / max(1, max(1, $exp->diffInDays($row->created_at ?? now()))) * 100)) : 100,
                'demo' => $lifecycle === 'demo',
                'active' => $lifecycle === 'active' || $lifecycle === 'demo',
                'expired' => $lifecycle === 'expired',
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
            'license_key' => 'required|string|max:255',
            'expires_at' => 'nullable|date',
            'max_users' => 'nullable|integer|min:0',
            'meta' => 'nullable|array',
        ]);

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

        $row = CoreLicense::query()->updateOrCreate(
            ['domain' => $data['domain'], 'license_key' => $data['license_key']],
            [
                'status' => 'active',
                'expires_at' => $data['expires_at'] ?? null,
                'max_users' => $data['max_users'] ?? 0,
                'meta' => $meta,
                'created_by' => null,
            ]
        );

        CoreLicenseMetaNormalizer::forgetCheckCache($data['domain'], $data['license_key']);

        return response()->json([
            'data' => [
                'status' => 'ok',
                'message' => 'License stored',
                'license_id' => $row->id,
            ],
        ]);
    }
}
