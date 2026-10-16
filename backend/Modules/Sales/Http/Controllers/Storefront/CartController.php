<?php

namespace Modules\Sales\Http\Controllers\Storefront;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Sales\Entities\SalesCart;
use Modules\Sales\Entities\SalesCartItem;
use Modules\Sales\Http\Controllers\Controller;
use Modules\Sales\Services\Storefront\StorefrontCatalog;

/**
 * Shop cart: one per signed-in user, or a guest cart addressed by the `X-Cart-Token` header.
 * When a guest signs in, the guest cart is merged into the user cart.
 */
class CartController extends Controller
{
    private const MAX_QTY = 99;

    public function __construct(private StorefrontCatalog $catalog) {}

    public function show(Request $request): JsonResponse
    {
        return $this->respond($this->cartFor($request, false));
    }

    public function applyCoupon(Request $request): JsonResponse
    {
        $data = $request->validate(['coupon_code' => 'nullable|string|max:64']);
        $cart = $this->cartFor($request);
        $code = trim((string) ($data['coupon_code'] ?? ''));
        if ($code !== '') {
            $quote = $this->catalog->quote($this->activeLines($cart), $code);
            if ($quote['coupon_error']) {
                return response()->json(['message' => $quote['coupon_error'], 'errors' => ['coupon_code' => [$quote['coupon_error']]]], 422);
            }
        }
        $cart->update(['coupon_code' => $code !== '' ? mb_strtoupper($code) : null]);

        return $this->respond($cart);
    }

    public function clear(Request $request): JsonResponse
    {
        $cart = $this->cartFor($request);
        SalesCartItem::query()->where('cart_id', $cart->id)->where('saved_for_later', false)->delete();
        $cart->update(['coupon_code' => null]);

        return $this->respond($cart);
    }

    public function addItem(Request $request): JsonResponse
    {
        $data = $request->validate(['product_id' => 'required_without:slug|nullable|integer', 'slug' => 'required_without:product_id|nullable|string|max:191', 'quantity' => 'nullable|integer|min:1|max:'.self::MAX_QTY]);
        $product = ! empty($data['product_id']) ? (clone $this->catalog->query())->findOrFail($data['product_id']) : $this->catalog->findBySlug((string) $data['slug']);
        abort_unless($this->catalog->available($product), 422, 'این کالا موجود نیست.');
        $cart = $this->cartFor($request);
        $line = SalesCartItem::query()->firstOrNew(['cart_id' => $cart->id, 'product_id' => $product->id]);
        $single = in_array(($product->meta['kind'] ?? ''), ['course', 'license', 'digital'], true) || ! empty($product->meta['single']);
        $line->quantity = $single ? 1 : min(self::MAX_QTY, ($line->exists && ! $line->saved_for_later ? $line->quantity : 0) + ($data['quantity'] ?? 1));
        $line->saved_for_later = false;
        $line->save();
        $cart->touch();

        return $this->respond($cart);
    }

    public function setQuantity(Request $request, int $product): JsonResponse
    {
        $data = $request->validate(['quantity' => 'required|integer|min:0|max:'.self::MAX_QTY]);
        $cart = $this->cartFor($request);
        $line = SalesCartItem::query()->where('cart_id', $cart->id)->where('product_id', $product)->firstOrFail();
        $data['quantity'] === 0 ? $line->delete() : $line->update(['quantity' => $data['quantity']]);

        return $this->respond($cart);
    }

    public function removeItem(Request $request, int $product): JsonResponse
    {
        $cart = $this->cartFor($request);
        SalesCartItem::query()->where('cart_id', $cart->id)->where('product_id', $product)->delete();

        return $this->respond($cart);
    }

    public function savedForLater(Request $request): JsonResponse
    {
        $cart = $this->cartFor($request);
        $rows = SalesCartItem::query()->with('product')->where('cart_id', $cart->id)->where('saved_for_later', true)->get();

        return response()->json(['data' => $rows->filter(fn ($l) => $l->product)->map(fn ($l) => $this->line($l))->values()]);
    }

    public function saveForLater(Request $request, int $product): JsonResponse
    {
        $cart = $this->cartFor($request);
        SalesCartItem::query()->where('cart_id', $cart->id)->where('product_id', $product)->firstOrFail()->update(['saved_for_later' => true]);

        return $this->respond($cart);
    }

    public function moveToCart(Request $request, int $product): JsonResponse
    {
        $cart = $this->cartFor($request);
        SalesCartItem::query()->where('cart_id', $cart->id)->where('product_id', $product)->firstOrFail()->update(['saved_for_later' => false]);

        return $this->respond($cart);
    }

    public static function user(Request $request): ?\App\Models\User
    {
        $user = $request->user() ?? auth('sanctum')->user();

        return $user instanceof \App\Models\User ? $user : null;
    }

    public static function token(Request $request): ?string
    {
        $token = (string) $request->header('X-Cart-Token', '');

        return preg_match('/^[A-Za-z0-9]{32,64}$/', $token) ? $token : null;
    }

    public function cartFor(Request $request, bool $create = true): SalesCart
    {
        $user = self::user($request);
        $token = self::token($request);
        if ($user) {
            $cart = SalesCart::query()->firstOrCreate(['user_id' => $user->id]);
            if ($token) {
                // Orders placed as a guest in this browser move to the account that just signed in.
                \Modules\Sales\Entities\SalesStorefrontOrder::query()->whereNull('user_id')->where('guest_token', $token)->update(['user_id' => $user->id]);
            }
            if ($token && ($guest = SalesCart::query()->whereNull('user_id')->where('token', $token)->first())) {
                foreach (SalesCartItem::query()->where('cart_id', $guest->id)->get() as $line) {
                    $own = SalesCartItem::query()->firstOrNew(['cart_id' => $cart->id, 'product_id' => $line->product_id]);
                    $own->quantity = min(self::MAX_QTY, max((int) $own->quantity, (int) $line->quantity));
                    $own->saved_for_later = false;
                    $own->save();
                }
                $cart->coupon_code ??= $guest->coupon_code;
                $cart->save();
                $guest->delete();
            }

            return $cart;
        }
        if ($token) {
            return SalesCart::query()->firstOrCreate(['token' => $token, 'user_id' => null]);
        }
        $cart = new SalesCart(['token' => \Illuminate\Support\Str::random(40)]);
        if ($create) {
            $cart->save();
        }

        return $cart;
    }

    /** @return list<array{item: \Modules\Sales\Entities\SalesCatalogItem, quantity: int}> */
    public function activeLines(SalesCart $cart): array
    {
        return SalesCartItem::query()->with('product')->where('cart_id', $cart->id)->where('saved_for_later', false)->get()
            ->filter(fn ($l) => $l->product && $l->product->status === 'active' && ($l->product->meta['storefront'] ?? false))
            ->map(fn ($l) => ['item' => $l->product, 'quantity' => (int) $l->quantity, 'line' => $l])->values()->all();
    }

    private function respond(SalesCart $cart): JsonResponse
    {
        $lines = $cart->exists ? $this->activeLines($cart) : [];

        return response()->json(['data' => [
            'id' => $cart->id,
            'token' => $cart->user_id ? null : $cart->token,
            'authenticated' => $cart->user_id !== null,
            'coupon_code' => $cart->coupon_code,
            'items' => array_map(fn ($l) => $this->line($l['line']), $lines),
            'pricing' => $this->catalog->quote($lines, $cart->coupon_code),
        ]]);
    }

    /** @return array<string, mixed> */
    private function line(SalesCartItem $line): array
    {
        $product = $this->catalog->serialize($line->product);
        $unit = $this->catalog->unitPrice($line->product);

        return ['id' => $line->id, 'product_id' => $line->product_id, 'quantity' => (int) $line->quantity, 'unit_price_minor' => $unit, 'line_total_minor' => $unit * (int) $line->quantity, 'product' => $product];
    }
}
