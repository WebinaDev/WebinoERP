<?php

namespace Modules\Marketplace\Http\Controllers;

use App\Http\Controllers\Api\PaginatesApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\Integrations\Entities\IntegrationSetting;
use Modules\Marketplace\Entities\MarketplaceEntitlement;
use Modules\Marketplace\Entities\MarketplaceModule;
use Modules\Marketplace\Entities\MarketplaceOrder;
use Modules\Marketplace\Services\MarketplaceLicenseService;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;
use Throwable;

class OrderController extends Controller
{
    use PaginatesApi;

    public function index(Request $request): JsonResponse
    {
        $query = MarketplaceOrder::query()->with('items')->orderByDesc('created_at');
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $orders = $query->paginate($this->perPage($request));
        $entitlements = MarketplaceEntitlement::query()
            ->with('module:id,slug,name,version')
            ->orderByDesc('updated_at')
            ->limit(500)
            ->get();

        return response()->json([
            'data' => [
                'orders' => $orders->items(),
                'entitlements' => $entitlements,
            ],
            'meta' => [
                'current_page' => $orders->currentPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'last_page' => $orders->lastPage(),
                'from' => $orders->firstItem(),
                'to' => $orders->lastItem(),
            ],
            'links' => [
                'first' => $orders->url(1),
                'last' => $orders->url(max($orders->lastPage(), 1)),
                'prev' => $orders->previousPageUrl(),
                'next' => $orders->nextPageUrl(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_number' => 'nullable|string|max:50|unique:marketplace_orders,order_number',
            'total' => 'nullable|numeric|min:0',
            'status' => 'nullable|string|in:pending,paid,cancelled,fulfilled',
            'user_id' => 'nullable|exists:users,id',
            'crm_account_id' => 'nullable|integer',
            'site_provision_id' => 'nullable|integer',
            'license_id' => 'nullable|integer',
            'items' => 'nullable|array|min:1',
            'items.*.module_id' => 'nullable|integer|exists:marketplace_modules,id',
            'items.*.module_slug' => 'nullable|string|max:64',
            'items.*.release_id' => 'nullable|integer',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.quantity' => 'nullable|integer|min:1',
        ]);

        if (empty($data['order_number'])) {
            $data['order_number'] = 'ORD-'.now()->format('Ymd').'-'.str_pad((string) (MarketplaceOrder::query()->count() + 1), 4, '0', STR_PAD_LEFT);
        }
        $data['status'] = $data['status'] ?? 'pending';
        if (! isset($data['user_id'])) {
            $data['user_id'] = $request->user()?->id;
        }

        $items = $data['items'] ?? [];
        unset($data['items']);

        $order = MarketplaceOrder::create($data);
        $total = 0.0;

        foreach ($items as $item) {
            $module = null;
            if (! empty($item['module_id'])) {
                $module = MarketplaceModule::query()->find($item['module_id']);
            } elseif (! empty($item['module_slug'])) {
                $module = MarketplaceModule::query()->where('slug', $item['module_slug'])->first();
            }
            if (! $module) {
                continue;
            }
            $qty = (int) ($item['quantity'] ?? 1);
            $price = isset($item['unit_price']) ? (float) $item['unit_price'] : (float) $module->price;
            $order->items()->create([
                'module_id' => $module->id,
                'module_slug' => $module->slug,
                'release_id' => $item['release_id'] ?? null,
                'unit_price' => $price,
                'quantity' => $qty,
            ]);
            $total += $price * $qty;
        }

        if (! isset($data['total']) || (float) ($data['total'] ?? 0) <= 0) {
            $order->update(['total' => $total]);
        }

        return response()->json(['data' => $order->fresh('items'), 'message' => 'Created'], 201);
    }

    /**
     * Create a module purchase order for a site and optionally start Zarinpal payment.
     */
    public function purchase(Request $request, MarketplaceLicenseService $licenses): JsonResponse
    {
        $data = $request->validate([
            'site_provision_id' => 'required|integer',
            'module_id' => 'nullable|integer|exists:marketplace_modules,id',
            'module_slug' => 'nullable|string|max:64',
            'release_id' => 'nullable|integer',
            'pay' => 'nullable|boolean',
            'callback_url' => 'nullable|url',
            'mark_paid' => 'nullable|boolean',
        ]);

        $module = null;
        if (! empty($data['module_id'])) {
            $module = MarketplaceModule::query()->findOrFail($data['module_id']);
        } elseif (! empty($data['module_slug'])) {
            $module = MarketplaceModule::query()->where('slug', $data['module_slug'])->firstOrFail();
        } else {
            return response()->json(['message' => 'module_id or module_slug required'], 422);
        }

        $site = WebinoSiteProvision::query()->findOrFail($data['site_provision_id']);

        $order = MarketplaceOrder::create([
            'order_number' => 'ORD-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4)),
            'total' => (float) $module->price,
            'status' => 'pending',
            'user_id' => $request->user()?->id,
            'crm_account_id' => $site->crm_account_id,
            'site_provision_id' => $site->id,
            'license_id' => $site->license_id,
            'payment_gateway' => 'zarinpal',
        ]);

        $order->items()->create([
            'module_id' => $module->id,
            'module_slug' => $module->slug,
            'release_id' => $data['release_id'] ?? null,
            'unit_price' => (float) $module->price,
            'quantity' => 1,
        ]);

        // Free modules or admin mark_paid: grant immediately.
        if ((float) $module->price <= 0 || ! empty($data['mark_paid'])) {
            $order->update([
                'status' => 'paid',
                'paid_at' => now(),
                'payment_ref' => 'manual-'.Str::random(8),
            ]);
            $license = $licenses->grantFromOrder($order->fresh('items'));

            return response()->json([
                'data' => [
                    'order' => $order->fresh('items'),
                    'license' => $license,
                    'payment' => null,
                ],
                'message' => 'Module licensed',
            ], 201);
        }

        if (! ($data['pay'] ?? true)) {
            return response()->json(['data' => ['order' => $order->fresh('items')], 'message' => 'Order created'], 201);
        }

        $payment = $this->initiateZarinpal(
            (float) $module->price,
            $data['callback_url'] ?? url('/api/v1/marketplace/orders/'.$order->id.'/payment/callback'),
            'Marketplace module: '.$module->slug,
            $request->user()?->id,
            $order->id,
        );

        $order->update(['payment_ref' => $payment['authority'] ?? null]);

        return response()->json([
            'data' => [
                'order' => $order->fresh('items'),
                'payment' => $payment,
            ],
            'message' => 'Payment initiated',
        ], 201);
    }

    public function paymentCallback(Request $request, MarketplaceOrder $order, MarketplaceLicenseService $licenses): JsonResponse
    {
        $authority = $request->input('authority') ?? $request->input('Authority');
        $payload = is_string($authority) ? Cache::pull('payment:'.$authority) : null;
        $status = (string) ($request->input('status') ?? $request->input('Status') ?? '');

        $verified = $payload !== null
            && ($payload['marketplace_order_id'] ?? null) == $order->id
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

    public function grantLicense(MarketplaceOrder $order, MarketplaceLicenseService $licenses): JsonResponse
    {
        if (! in_array($order->status, ['paid', 'fulfilled'], true)) {
            return response()->json(['message' => 'Order must be paid before granting license'], 422);
        }

        try {
            $license = $licenses->grantFromOrder($order->fresh('items'));
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => ['order' => $order->fresh('items'), 'license' => $license]]);
    }

    public function show(MarketplaceOrder $order): JsonResponse
    {
        $entitlements = MarketplaceEntitlement::query()
            ->with('module:id,slug,name,version')
            ->where('order_id', $order->id)
            ->get();

        return response()->json([
            'data' => [
                'order' => $order->load('items'),
                'entitlements' => $entitlements,
            ],
        ]);
    }

    public function update(Request $request, MarketplaceOrder $order): JsonResponse
    {
        $data = $request->validate([
            'order_number' => 'sometimes|string|max:50|unique:marketplace_orders,order_number,'.$order->id,
            'total' => 'nullable|numeric|min:0',
            'status' => 'sometimes|string|in:pending,paid,cancelled,fulfilled',
            'user_id' => 'nullable|exists:users,id',
        ]);
        $order->update($data);

        return response()->json(['data' => $order->fresh('items'), 'message' => 'Updated']);
    }

    public function destroy(MarketplaceOrder $order): Response
    {
        $order->delete();

        return response()->noContent();
    }

    /**
     * @return array{payment_id: string, authority: string, redirect_url: string, merchant_id: string}
     */
    protected function initiateZarinpal(float $amount, string $callback, string $description, ?int $userId, int $orderId): array
    {
        $settings = IntegrationSetting::getJson('payment', 'zarinpal', []);
        $merchantId = $settings['merchant_id'] ?? env('ZARINPAL_MERCHANT_ID', 'sandbox');
        $sandbox = $merchantId === 'sandbox' || (bool) ($settings['sandbox'] ?? env('ZARINPAL_SANDBOX', true));
        $base = $sandbox ? 'https://sandbox.zarinpal.com/pg/v4/payment/' : 'https://api.zarinpal.com/pg/v4/payment/';
        $paymentId = 'pay_'.Str::uuid()->toString();

        if ($merchantId !== 'sandbox' && ! $sandbox) {
            $res = Http::asJson()->post($base.'request.json', [
                'merchant_id' => $merchantId,
                'amount' => (int) round($amount),
                'callback_url' => $callback,
                'description' => $description,
            ]);
            $json = $res->json();
            if (($json['data']['code'] ?? 0) !== 100) {
                throw new \RuntimeException('Zarinpal init failed');
            }
            $authority = (string) ($json['data']['authority'] ?? '');
            Cache::put('payment:'.$authority, [
                'payment_id' => $paymentId,
                'amount' => $amount,
                'merchant_id' => $merchantId,
                'user_id' => $userId,
                'marketplace_order_id' => $orderId,
            ], now()->addHours(1));
            $redirectUrl = 'https://www.zarinpal.com/pg/StartPay/'.$authority;

            return [
                'payment_id' => $paymentId,
                'authority' => $authority,
                'redirect_url' => $redirectUrl,
                'merchant_id' => $merchantId,
            ];
        }

        $authority = 'A'.Str::upper(Str::random(31));
        Cache::put('payment:'.$authority, [
            'payment_id' => $paymentId,
            'amount' => $amount,
            'merchant_id' => $merchantId,
            'user_id' => $userId,
            'marketplace_order_id' => $orderId,
        ], now()->addHours(1));

        $redirectUrl = $callback.(str_contains($callback, '?') ? '&' : '?').'Authority='.$authority.'&Status=OK';

        return [
            'payment_id' => $paymentId,
            'authority' => $authority,
            'redirect_url' => $redirectUrl,
            'merchant_id' => $merchantId,
        ];
    }
}
