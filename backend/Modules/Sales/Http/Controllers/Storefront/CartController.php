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
        $data = $request->validate([
            'product_id' => 'required_without:slug|nullable|integer', 'slug' => 'required_without:product_id|nullable|string|max:191',
            'quantity' => 'nullable|integer|min:1|max:'.self::MAX_QTY, 'variant_id' => 'nullable|string|max:40',
        ]);
        $product = ! empty($data['product_id']) ? (clone $this->catalog->query())->findOrFail($data['product_id']) : $this->catalog->findBySlug((string) $data['slug']);
        $variant = $this->resolveVariant($product, $data['variant_id'] ?? null);
        abort_unless($this->catalog->available($product, $variant), 422, 'این کالا موجود نیست.');
        $cart = $this->cartFor($request);
        $line = SalesCartItem::query()->firstOrNew(['cart_id' => $cart->id, 'product_id' => $product->id, 'variant_key' => $variant['id'] ?? '']);
        $single = in_array(($product->meta['kind'] ?? ''), ['course', 'license', 'digital'], true) || ! empty($product->meta['single']);
        $qty = $single ? 1 : min(self::MAX_QTY, ($line->exists && ! $line->saved_for_later ? $line->quantity : 0) + ($data['quantity'] ?? 1));
        $left = $this->catalog->stockLeft($product, $variant);
        if ($left !== null && $qty > $left) {
            return response()->json(['message' => 'موجودی این کالا کافی نیست.', 'errors' => ['quantity' => ['حداکثر '.$left.' عدد موجود است.']]], 422);
        }
        $line->quantity = $qty;
        $line->saved_for_later = false;
        $line->save();
        $cart->touch();

        return $this->respond($cart);
    }

    public function setQuantity(Request $request, int $product): JsonResponse
    {
        $data = $request->validate(['quantity' => 'required|integer|min:0|max:'.self::MAX_QTY, 'variant_id' => 'nullable|string|max:40']);
        $cart = $this->cartFor($request);
        $line = $this->lineByProduct($cart, $product, $data['variant_id'] ?? $request->query('variant_id'));

        return $this->applyQuantity($cart, $line, (int) $data['quantity']);
    }

    public function removeItem(Request $request, int $product): JsonResponse
    {
        $cart = $this->cartFor($request);
        $variant = $request->input('variant_id', $request->query('variant_id'));
        SalesCartItem::query()->where('cart_id', $cart->id)->where('product_id', $product)
            ->when(is_string($variant) && $variant !== '', fn ($q) => $q->where('variant_key', $variant))->delete();

        return $this->respond($cart);
    }

    /** PUT /cart/lines/{line}: quantity of one cart line (one product variant). */
    public function setLineQuantity(Request $request, int $line): JsonResponse
    {
        $data = $request->validate(['quantity' => 'required|integer|min:0|max:'.self::MAX_QTY]);
        $cart = $this->cartFor($request);
        $row = SalesCartItem::query()->where('cart_id', $cart->id)->whereKey($line)->firstOrFail();

        return $this->applyQuantity($cart, $row, (int) $data['quantity']);
    }

    public function removeLine(Request $request, int $line): JsonResponse
    {
        $cart = $this->cartFor($request);
        SalesCartItem::query()->where('cart_id', $cart->id)->whereKey($line)->delete();

        return $this->respond($cart);
    }

    private function applyQuantity(SalesCart $cart, SalesCartItem $line, int $qty): JsonResponse
    {
        if ($qty === 0) {
            $line->delete();

            return $this->respond($cart);
        }
        $product = $line->product;
        if ($product) {
            $left = $this->catalog->stockLeft($product, $this->catalog->variant($product, $line->variant_key ?: null));
            if ($left !== null && $qty > $left) {
                return response()->json(['message' => 'موجودی این کالا کافی نیست.', 'errors' => ['quantity' => ['حداکثر '.$left.' عدد موجود است.']]], 422);
            }
        }
        $line->update(['quantity' => $qty]);

        return $this->respond($cart);
    }

    /** A cart line by product; with several variants in the cart, variant_id picks one (first line otherwise). */
    private function lineByProduct(SalesCart $cart, int $product, mixed $variant): SalesCartItem
    {
        return SalesCartItem::query()->where('cart_id', $cart->id)->where('product_id', $product)
            ->when(is_string($variant) && $variant !== '', fn ($q) => $q->where('variant_key', $variant))
            ->orderBy('id')->firstOrFail();
    }

    /** @return array<string, mixed>|null */
    private function resolveVariant(\Modules\Sales\Entities\SalesCatalogItem $product, ?string $key): ?array
    {
        $all = $this->catalog->variants($product);
        if ($all === []) {
            return null;
        }
        if ($key !== null && $key !== '') {
            $v = $this->catalog->variant($product, $key);
            if (! $v) {
                abort(response()->json(['message' => 'این گزینه برای این محصول وجود ندارد.', 'errors' => ['variant_id' => ['این گزینه برای این محصول وجود ندارد.']]], 422));
            }

            return $v;
        }
        $default = $this->catalog->defaultVariant($product) ?? (count($all) === 1 ? $all[0] : null);
        if (! $default) {
            abort(response()->json(['message' => 'لطفا یکی از گزینه‌های محصول را انتخاب کنید.', 'errors' => ['variant_id' => ['لطفا یکی از گزینه‌های محصول را انتخاب کنید.']]], 422));
        }

        return $default;
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
        $this->lineByProduct($cart, $product, $request->input('variant_id', $request->query('variant_id')))->update(['saved_for_later' => true]);

        return $this->respond($cart);
    }

    public function moveToCart(Request $request, int $product): JsonResponse
    {
        $cart = $this->cartFor($request);
        $this->lineByProduct($cart, $product, $request->input('variant_id', $request->query('variant_id')))->update(['saved_for_later' => false]);

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
                    $own = SalesCartItem::query()->firstOrNew(['cart_id' => $cart->id, 'product_id' => $line->product_id, 'variant_key' => (string) $line->variant_key]);
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

    /** @return list<array{item: \Modules\Sales\Entities\SalesCatalogItem, quantity: int, variant: ?array<string, mixed>, line: SalesCartItem}> */
    public function activeLines(SalesCart $cart): array
    {
        return SalesCartItem::query()->with('product')->where('cart_id', $cart->id)->where('saved_for_later', false)->orderBy('id')->get()
            ->filter(fn ($l) => $l->product && $l->product->status === 'active' && ($l->product->meta['storefront'] ?? false))
            // A line whose variant was removed from the product drops out of the cart.
            ->filter(fn ($l) => ! $l->variant_key || $this->catalog->variant($l->product, $l->variant_key))
            ->map(fn ($l) => ['item' => $l->product, 'quantity' => (int) $l->quantity, 'variant' => $l->variant_key ? $this->catalog->variant($l->product, $l->variant_key) : null, 'line' => $l])->values()->all();
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
            'pricing' => $this->catalog->quote($lines, $cart->coupon_code, [
                'shipping_method' => $cart->meta['checkout']['shipping_method'] ?? null,
                'gift_card_code' => $cart->meta['checkout']['gift_card_code'] ?? null,
            ]),
        ]]);
    }

    /** @return array<string, mixed> */
    private function line(SalesCartItem $line): array
    {
        $product = $this->catalog->serialize($line->product);
        $variant = $line->variant_key ? $this->catalog->variant($line->product, $line->variant_key) : null;
        $unit = $this->catalog->unitPrice($line->product, $variant);
        $regular = max($unit, $this->catalog->regularPrice($line->product, $variant));

        return [
            'id' => $line->id, 'product_id' => $line->product_id, 'variant_id' => $variant['id'] ?? null, 'variant_name' => $variant['name'] ?? null,
            'quantity' => (int) $line->quantity, 'unit_price_minor' => $unit, 'regular_price_minor' => $regular, 'on_sale' => $regular > $unit,
            'line_total_minor' => $unit * (int) $line->quantity, 'stock' => $this->catalog->stockLeft($line->product, $variant), 'product' => $product,
        ];
    }
}
