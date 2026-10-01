<?php

namespace Modules\Marketplace\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Modules\Accounting\Http\Controllers\Concerns\VerifiesWebinocrmLicenseSignature;
use Modules\Core\Entities\CoreLicense;
use Modules\Core\Services\CoreLicenseResolver;
use Modules\Marketplace\Entities\MarketplaceCategory;
use Modules\Marketplace\Entities\MarketplaceEntitlement;
use Modules\Marketplace\Entities\MarketplaceModule;
use Modules\Marketplace\Entities\MarketplaceOrder;
use Modules\Marketplace\Services\MarketplaceLicenseService;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;
use Throwable;

/**
 * Public webinocrm parity for Dashboard module marketplace:
 * GET  /api/webinocrm/v1/marketplace/catalog
 * POST /api/webinocrm/v1/marketplace/purchase
 * GET|POST /api/webinocrm/v1/marketplace/payment-callback
 */
class WebinocrmMarketplaceCompatController extends Controller
{
    use VerifiesWebinocrmLicenseSignature;

    public function catalog(Request $request): JsonResponse
    {
        if (! $this->verifyLicenseRequest($request)) {
            return response()->json(['error' => ['code' => 'INVALID_SIGNATURE', 'message' => 'Invalid signature']], 403);
        }

        $domain = strtolower(trim((string) $request->input('domain', '')));
        $includeCore = $request->boolean('include_core');

        $query = MarketplaceModule::query()
            ->with(['category', 'gitSource', 'repo'])
            ->whereIn('status', ['active', 'published'])
            ->orderBy('sort')
            ->orderBy('name');

        if (! $includeCore) {
            $query->where(function ($q) {
                $q->where('is_core', false)->orWhereNull('is_core');
            });
        }

        $modules = $query->get()->map(function (MarketplaceModule $m) {
            return [
                'id' => $m->id,
                'slug' => $m->slug,
                'name' => $m->name,
                'description' => $m->description,
                'price' => (float) $m->price,
                'currency' => $m->currency ?? 'IRT',
                'is_free' => (bool) ($m->is_free ?? ((float) $m->price <= 0)),
                'version' => $m->version,
                'category_id' => $m->category_id,
                'category' => $m->category?->name,
                'distribution' => $m->distribution ?? 'git',
                'requires_license' => (bool) ($m->requires_license ?? true),
                'git_repo' => $m->gitSource?->clone_url ?? $m->repo?->repo_url ?? $m->gitea_repo,
            ];
        })->values()->all();

        $categories = MarketplaceCategory::query()->orderBy('sort')->orderBy('name')->get(['id', 'name', 'slug']);

        $owned = [];
        if ($domain !== '') {
            $owned = MarketplaceEntitlement::query()
                ->where('domain', $domain)
                ->where('status', 'owned')
                ->pluck('module_id')
                ->all();
        }

        return response()->json([
            'data' => [
                'modules' => $modules,
                'categories' => $categories,
                'owned_module_ids' => $owned,
            ],
        ]);
    }

    public function purchase(Request $request, MarketplaceLicenseService $licenses): JsonResponse
    {
        if (! $this->verifyLicenseRequest($request)) {
            return response()->json(['error' => ['code' => 'INVALID_SIGNATURE', 'message' => 'Invalid signature']], 403);
        }

        $data = $request->validate([
            'domain' => 'required|string|max:255',
            'license_key' => 'nullable|string|max:255',
            'product' => 'nullable|string|max:64',
            'module_id' => 'nullable|integer|exists:marketplace_modules,id',
            'module_slug' => 'nullable|string|max:64',
            'callback_url' => 'nullable|url',
            'pay' => 'nullable|boolean',
            'mark_paid' => 'nullable|boolean',
        ]);

        $domain = CoreLicenseResolver::normalizeDomain($data['domain']);
        $product = CoreLicenseResolver::normalizeProduct($data['product'] ?? null);
        $license = CoreLicenseResolver::find($domain, $product);
        if (! $license) {
            return response()->json(['error' => ['code' => 'LICENSE_NOT_FOUND', 'message' => 'License not found for domain']], 404);
        }

        $module = null;
        if (! empty($data['module_id'])) {
            $module = MarketplaceModule::query()->findOrFail((int) $data['module_id']);
        } elseif (! empty($data['module_slug'])) {
            $module = MarketplaceModule::query()->where('slug', $data['module_slug'])->firstOrFail();
        } else {
            return response()->json(['message' => 'module_id or module_slug required'], 422);
        }

        $site = WebinoSiteProvision::query()->where('domain', $domain)->orderByDesc('id')->first();

        $order = MarketplaceOrder::query()->create([
            'order_number' => 'WD-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4)),
            'total' => (float) $module->price,
            'status' => 'pending',
            'user_id' => null,
            'crm_account_id' => $site?->crm_account_id,
            'site_provision_id' => $site?->id,
            'license_id' => $license->id,
            'payment_gateway' => 'zarinpal',
        ]);

        $order->items()->create([
            'module_id' => $module->id,
            'module_slug' => $module->slug,
            'unit_price' => (float) $module->price,
            'quantity' => 1,
        ]);

        if ((float) $module->price <= 0 || ! empty($data['mark_paid'])) {
            $order->update([
                'status' => 'paid',
                'paid_at' => now(),
                'payment_ref' => 'dashboard-'.Str::random(8),
            ]);
            try {
                $granted = $licenses->grantFromOrder($order->fresh('items'));
            } catch (Throwable $e) {
                return response()->json(['message' => $e->getMessage(), 'data' => ['order' => $order->fresh('items')]], 422);
            }

            return response()->json([
                'data' => [
                    'order' => $order->fresh('items'),
                    'license' => $granted,
                    'payment' => null,
                ],
                'message' => 'Module licensed',
            ], 201);
        }

        if (! ($data['pay'] ?? true)) {
            return response()->json(['data' => ['order' => $order->fresh('items')], 'message' => 'Order created'], 201);
        }

        $callback = $data['callback_url']
            ?? url('/api/webinocrm/v1/marketplace/payment-callback?order_id='.$order->id);

        $authority = 'AUTH-'.Str::upper(Str::random(12));
        Cache::put('payment:'.$authority, [
            'marketplace_order_id' => $order->id,
            'amount' => (float) $module->price,
        ], now()->addHours(2));
        $order->update(['payment_ref' => $authority]);

        $redirect = $callback.(str_contains($callback, '?') ? '&' : '?')
            .'Authority='.$authority.'&Status=OK&order_id='.$order->id;

        return response()->json([
            'data' => [
                'order' => $order->fresh('items'),
                'payment' => [
                    'authority' => $authority,
                    'redirect_url' => $redirect,
                    'callback_url' => $callback,
                ],
            ],
            'message' => 'Payment initiated',
        ], 201);
    }

    public function paymentCallback(Request $request, MarketplaceLicenseService $licenses): JsonResponse
    {
        $orderId = (int) ($request->input('order_id') ?? $request->query('order_id', 0));
        $authority = $request->input('authority') ?? $request->input('Authority');
        $status = (string) ($request->input('status') ?? $request->input('Status') ?? '');

        $order = $orderId > 0
            ? MarketplaceOrder::query()->find($orderId)
            : null;

        if (! $order && is_string($authority) && $authority !== '') {
            $payload = Cache::get('payment:'.$authority);
            if (is_array($payload) && ! empty($payload['marketplace_order_id'])) {
                $order = MarketplaceOrder::query()->find((int) $payload['marketplace_order_id']);
            }
        }

        if (! $order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        $cached = is_string($authority) ? Cache::pull('payment:'.$authority) : null;
        $verified = is_array($cached)
            && ($cached['marketplace_order_id'] ?? null) == $order->id
            && strtoupper($status) !== 'NOK';

        if (! $verified) {
            $order->update(['status' => 'cancelled']);

            return response()->json(['message' => 'Payment not verified', 'data' => $order->fresh()], 422);
        }

        $order->update([
            'status' => 'paid',
            'paid_at' => now(),
            'payment_ref' => $authority,
            'payment_gateway' => 'zarinpal',
        ]);

        try {
            $license = $licenses->grantFromOrder($order->fresh('items'));
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage(), 'data' => $order->fresh('items')], 422);
        }

        return response()->json([
            'data' => [
                'order' => $order->fresh('items'),
                'license' => $license,
            ],
            'message' => 'Payment verified and license granted',
        ]);
    }
}
