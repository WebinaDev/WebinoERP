<?php

namespace Modules\Sales\Http\Controllers\Storefront;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Integrations\Services\Payments\GatewayConfigStore;
use Modules\Integrations\Services\Payments\PaymentException;
use Modules\Integrations\Services\Payments\PaymentOrchestrator;
use Modules\Sales\Entities\SalesCartItem;
use Modules\Sales\Entities\SalesCoupon;
use Modules\Sales\Entities\SalesInvoice;
use Modules\Sales\Entities\SalesStorefrontOrder;
use Modules\Sales\Http\Controllers\Controller;
use Modules\Sales\Services\Storefront\StorefrontCatalog;

/** Checkout → storefront order + sales invoice; payment goes through the Integrations gateways. */
class CheckoutController extends Controller
{
    public function __construct(private StorefrontCatalog $catalog, private CartController $carts) {}

    public function shippingQuote(Request $request): JsonResponse
    {
        $cart = $this->carts->cartFor($request);
        $quote = $this->catalog->quote($this->carts->activeLines($cart), $request->input('coupon_code', $cart->coupon_code));

        return response()->json(['data' => [
            'methods' => [['id' => 'post', 'title' => 'ارسال پستی', 'cost_minor' => $quote['shipping_minor']]],
            'shipping_minor' => $quote['shipping_minor'],
            'pricing' => $quote,
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'shipping_address' => 'nullable',
            'customer_phone' => 'nullable|string|max:32',
            'customer_note' => 'nullable|string|max:500',
            'coupon_code' => 'nullable|string|max:64',
            'shipping_state_code' => 'nullable|string|max:32',
            'shipping_postcode' => 'nullable|string|max:20',
        ]);
        if (isset($data['shipping_state_code']) && ! GeoController::valid($data['shipping_state_code'])) {
            return response()->json(['message' => 'استان نامعتبر است.', 'errors' => ['shipping_state_code' => ['استان نامعتبر است.']]], 422);
        }
        $user = $request->user();
        $cart = $this->carts->cartFor($request);
        $lines = $this->carts->activeLines($cart);
        abort_if($lines === [], 422, 'سبد خرید خالی است.');
        foreach ($lines as $line) {
            abort_unless($this->catalog->available($line['item']), 422, 'کالای «'.$line['item']->name.'» موجود نیست.');
        }
        $quote = $this->catalog->quote($lines, $data['coupon_code'] ?? $cart->coupon_code);
        if (! empty($data['coupon_code']) && $quote['coupon_error']) {
            return response()->json(['message' => $quote['coupon_error'], 'errors' => ['coupon_code' => [$quote['coupon_error']]]], 422);
        }
        $address = is_array($data['shipping_address'] ?? null) ? array_map(fn ($v) => is_scalar($v) ? mb_substr(strip_tags((string) $v), 0, 300) : null, array_slice($data['shipping_address'], 0, 20, true))
            : (isset($data['shipping_address']) ? ['line' => mb_substr(strip_tags((string) $data['shipping_address']), 0, 500)] : null);
        if ($address !== null) {
            $address['state_code'] = $data['shipping_state_code'] ?? null;
            $address['postcode'] = $data['shipping_postcode'] ?? null;
            $address['note'] = isset($data['customer_note']) ? strip_tags($data['customer_note']) : null;
        }

        $order = DB::transaction(function () use ($user, $cart, $lines, $quote, $address, $data) {
            $items = array_map(fn ($l) => [
                'product_id' => $l['item']->id, 'name' => $l['item']->name, 'sku' => $l['item']->sku,
                'quantity' => $l['quantity'], 'unit_price' => $this->catalog->unitPrice($l['item']),
                'total' => $this->catalog->unitPrice($l['item']) * $l['quantity'],
            ], $lines);
            $number = 'WS-'.now()->format('ymd').'-'.Str::upper(Str::random(5));
            $invoice = SalesInvoice::query()->create([
                'number' => $number, 'customer_name' => $user->name, 'customer_id' => null,
                'subtotal' => $quote['subtotal_minor'], 'discount' => $quote['discount_minor'], 'total' => $quote['total_minor'],
                'status' => 'unpaid', 'issue_date' => now()->toDateString(), 'payment_method' => 'online',
                'items' => array_merge(array_map(fn ($i) => ['title' => $i['name'], 'quantity' => $i['quantity'], 'unit_price' => $i['unit_price'], 'total' => $i['total']], $items),
                    $quote['shipping_minor'] > 0 ? [['title' => 'هزینه ارسال', 'quantity' => 1, 'unit_price' => $quote['shipping_minor'], 'total' => $quote['shipping_minor']]] : []),
                'notes' => 'سفارش فروشگاه آنلاین', 'created_by' => $user->id,
            ]);
            $order = SalesStorefrontOrder::query()->create([
                'number' => $number, 'user_id' => $user->id, 'status' => $quote['total_minor'] > 0 ? 'pending_payment' : 'processing',
                'payment_method' => 'online', 'items' => $items, 'address' => $address, 'phone' => $data['customer_phone'] ?? null,
                'subtotal' => $quote['subtotal_minor'], 'discount' => $quote['discount_minor'], 'shipping' => $quote['shipping_minor'],
                'total' => $quote['total_minor'], 'coupon_code' => $quote['coupon']['code'] ?? null, 'invoice_id' => $invoice->id,
            ]);
            if ($quote['coupon']) {
                SalesCoupon::query()->where('code', $quote['coupon']['code'])->increment('used');
            }
            foreach ($lines as $l) {
                $meta = (array) ($l['item']->meta ?? []);
                if (array_key_exists('stock', $meta) && $meta['stock'] !== null) {
                    $meta['stock'] = max(0, (int) $meta['stock'] - $l['quantity']);
                    $l['item']->forceFill(['meta' => $meta])->save();
                }
            }
            SalesCartItem::query()->where('cart_id', $cart->id)->where('saved_for_later', false)->delete();
            $cart->update(['coupon_code' => null]);

            return $order;
        });

        return response()->json(['data' => AccountController::orderPayload($order)], 201);
    }

    public function paymentOptions(Request $request, GatewayConfigStore $configs): JsonResponse
    {
        $mode = $request->query('mode');

        return response()->json(['data' => ['gateways' => $configs->optionsFor(is_string($mode) && $mode !== '' ? $mode : 'cash')]]);
    }

    public function paymentIntent(Request $request, PaymentOrchestrator $payments): JsonResponse
    {
        $data = $request->validate(['order_id' => 'required|integer', 'provider' => 'nullable|string|max:32', 'mode' => 'nullable|string|in:cash,installment', 'return_url' => 'nullable|url']);
        $order = SalesStorefrontOrder::query()->where('user_id', $request->user()->id)->findOrFail($data['order_id']);
        abort_unless($order->invoice_id && $order->status === 'pending_payment', 422, 'این سفارش قابل پرداخت نیست.');
        try {
            $intent = $payments->start([
                'payable_type' => 'sales_invoice', 'payable_id' => (string) $order->invoice_id,
                'mode' => $data['mode'] ?? 'cash', 'gateway' => $data['provider'] ?? null,
                'description' => 'سفارش '.$order->number, 'return_url' => $data['return_url'] ?? null,
                'mobile' => $order->phone,
            ], $request->user()->id);
        } catch (PaymentException $e) {
            return response()->json(['message' => $e->getMessage(), 'error' => $e->errorCode], $e->status);
        }
        $order->update(['payment_method' => (string) $intent->gateway]);

        return response()->json(['data' => $intent->toApiArray()]);
    }
}
