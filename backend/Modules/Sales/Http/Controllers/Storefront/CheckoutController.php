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
            'customer_name' => 'nullable|string|max:120',
            'customer_email' => 'nullable|email|max:191',
            'payment_mode' => 'nullable|in:cash,installment',
        ]);
        $settings = app(\Modules\Sales\Services\Storefront\StorefrontSettings::class);
        $user = CartController::user($request);
        if (! $user && $settings->get('require_login')) {
            return response()->json(['message' => 'برای ثبت سفارش ابتدا وارد حساب کاربری شوید.', 'error' => 'login_required'], 401);
        }
        $data['customer_name'] = trim(strip_tags((string) ($data['customer_name'] ?? ($user?->name ?? ''))));
        $data['customer_email'] = $data['customer_email'] ?? ($user?->email ?? null);
        $errors = [];
        if ($data['customer_name'] === '') {
            $errors['customer_name'] = ['نام و نام خانوادگی را وارد کنید.'];
        }
        $phone = preg_replace('/\D/', '', strtr((string) ($data['customer_phone'] ?? ''), ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9']));
        if (! preg_match('/^(09\d{9}|989\d{9})$/', (string) $phone)) {
            $errors['customer_phone'] = ['شماره موبایل معتبر نیست.'];
        }
        $data['customer_phone'] = $phone;
        if ($settings->get('require_email') && empty($data['customer_email'])) {
            $errors['customer_email'] = ['ایمیل را وارد کنید.'];
        }
        if ($errors !== []) {
            return response()->json(['message' => reset($errors)[0], 'errors' => $errors], 422);
        }
        if (isset($data['shipping_state_code']) && ! GeoController::valid($data['shipping_state_code'])) {
            return response()->json(['message' => 'استان نامعتبر است.', 'errors' => ['shipping_state_code' => ['استان نامعتبر است.']]], 422);
        }
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
        if ($quote['requires_shipping'] && $settings->get('require_address') && empty($address['line'] ?? $address)) {
            return response()->json(['message' => 'نشانی ارسال را وارد کنید.', 'errors' => ['shipping_address' => ['نشانی ارسال را وارد کنید.']]], 422);
        }
        if ($address !== null) {
            $address['state_code'] = $data['shipping_state_code'] ?? null;
            $address['postcode'] = $data['shipping_postcode'] ?? null;
            $address['note'] = isset($data['customer_note']) ? strip_tags($data['customer_note']) : null;
        }

        $order = DB::transaction(function () use ($user, $cart, $lines, $quote, $address, $data) {
            $courseSlugs = [];
            $items = array_map(fn ($l) => [
                'product_id' => $l['item']->id, 'name' => $l['item']->name, 'sku' => $l['item']->sku,
                'kind' => (string) ($l['item']->meta['kind'] ?? 'product'), 'course_slug' => $l['item']->meta['course_slug'] ?? null,
                'slug' => $this->catalog->slugOf($l['item']), 'image_url' => $l['item']->meta['image_url'] ?? null,
                'quantity' => $l['quantity'], 'unit_price' => $this->catalog->unitPrice($l['item']),
                'total' => $this->catalog->unitPrice($l['item']) * $l['quantity'],
            ], $lines);
            $number = 'WS-'.now()->format('ymd').'-'.Str::upper(Str::random(5));
            $invoice = SalesInvoice::query()->create([
                'number' => $number, 'customer_name' => $data['customer_name'], 'customer_id' => null,
                'subtotal' => $quote['subtotal_minor'], 'discount' => $quote['discount_minor'], 'total' => $quote['total_minor'],
                'status' => 'unpaid', 'issue_date' => now()->toDateString(), 'payment_method' => 'online',
                'items' => array_merge(array_map(fn ($i) => ['title' => $i['name'], 'quantity' => $i['quantity'], 'unit_price' => $i['unit_price'], 'total' => $i['total']], $items),
                    $quote['shipping_minor'] > 0 ? [['title' => 'هزینه ارسال', 'quantity' => 1, 'unit_price' => $quote['shipping_minor'], 'total' => $quote['shipping_minor']]] : []),
                'notes' => 'سفارش فروشگاه آنلاین'.($data['customer_phone'] ? ' | '.$data['customer_phone'] : ''), 'created_by' => $user?->id,
            ]);
            $order = SalesStorefrontOrder::query()->create([
                'number' => $number, 'user_id' => $user?->id, 'status' => $quote['total_minor'] > 0 ? 'pending_payment' : 'paid',
                'payment_method' => 'online', 'payment_mode' => $data['payment_mode'] ?? 'cash',
                'customer_name' => $data['customer_name'], 'customer_email' => $data['customer_email'] ?? null,
                'guest_token' => $user ? null : $cart->token, 'paid_at' => $quote['total_minor'] > 0 ? null : now(), 'items' => $items, 'address' => $address, 'phone' => $data['customer_phone'] ?? null,
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
        $settings = app(\Modules\Sales\Services\Storefront\StorefrontSettings::class);
        $all = $configs->optionsFor(null);
        $modes = array_keys(array_filter(['cash' => (bool) $settings->get('cash_enabled'), 'installment' => (bool) $settings->get('installment_enabled')]));
        $all = array_values(array_filter(array_map(function ($g) use ($modes) {
            $g['modes'] = array_values(array_intersect($g['modes'] ?? [], $modes));
            $g['cash_enabled'] = in_array('cash', $g['modes'], true);
            $g['installment_enabled'] = in_array('installment', $g['modes'], true);

            return $g;
        }, $all), fn ($g) => $g['modes'] !== []));
        if (is_string($mode) && $mode !== '') {
            $all = array_values(array_filter($all, fn ($g) => in_array($mode, $g['modes'], true)));
        }

        return response()->json(['data' => ['gateways' => $all, 'modes' => $modes]]);
    }

    public function paymentIntent(Request $request, PaymentOrchestrator $payments): JsonResponse
    {
        $data = $request->validate(['order_id' => 'required|integer', 'provider' => 'nullable|string|max:32', 'mode' => 'nullable|string|in:cash,installment']);
        $order = self::ownedOrder($request, (int) $data['order_id']);
        abort_unless($order->invoice_id && $order->status === 'pending_payment', 422, 'این سفارش قابل پرداخت نیست.');
        try {
            $intent = $payments->start([
                'payable_type' => 'sales_invoice', 'payable_id' => (string) $order->invoice_id,
                'mode' => $data['mode'] ?? 'cash', 'gateway' => $data['provider'] ?? null,
                'description' => 'سفارش '.$order->number,
                'return_url' => $request->getSchemeAndHttpHost().'/checkout/result?order='.$order->id.($order->guest_token ? '&number='.$order->number : ''),
                'mobile' => $order->phone,
            ], CartController::user($request)?->id);
        } catch (PaymentException $e) {
            return response()->json(['message' => $e->getMessage(), 'error' => $e->errorCode], $e->status);
        }
        $order->update(['payment_method' => (string) $intent->gateway, 'payment_mode' => $data['mode'] ?? 'cash']);

        return response()->json(['data' => $intent->toApiArray()]);
    }

    /** Order of the signed-in user, or of the guest who holds its cart token (or knows its number). */
    public static function ownedOrder(Request $request, int $id): SalesStorefrontOrder
    {
        $user = CartController::user($request);
        $token = CartController::token($request);
        $number = (string) $request->input('number', $request->query('number', ''));
        $order = SalesStorefrontOrder::query()->findOrFail($id);
        $ok = ($user && (int) $order->user_id === (int) $user->id)
            || ($order->user_id === null && (($token && hash_equals((string) $order->guest_token, $token)) || ($number !== '' && hash_equals((string) $order->number, $number))));
        abort_unless($ok, 404);

        return $order;
    }

    public function showOrder(Request $request, int $order): JsonResponse
    {
        return response()->json(['data' => AccountController::orderPayload(self::ownedOrder($request, $order))]);
    }
}
