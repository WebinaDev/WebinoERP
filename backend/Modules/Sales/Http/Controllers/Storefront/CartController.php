<?php

namespace Modules\Sales\Http\Controllers\Storefront;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Sales\Entities\SalesCart;
use Modules\Sales\Entities\SalesCartItem;
use Modules\Sales\Http\Controllers\Controller;
use Modules\Sales\Services\Storefront\StorefrontCatalog;

/** Signed-in customer's cart (one per user). */
class CartController extends Controller
{
    private const MAX_QTY = 99;

    public function __construct(private StorefrontCatalog $catalog) {}

    public function show(Request $request): JsonResponse
    {
        return $this->respond($this->cartFor($request));
    }

    public function addItem(Request $request): JsonResponse
    {
        $data = $request->validate(['product_id' => 'required|integer', 'quantity' => 'nullable|integer|min:1|max:'.self::MAX_QTY]);
        $product = (clone $this->catalog->query())->findOrFail($data['product_id']);
        abort_unless($this->catalog->available($product), 422, 'این کالا موجود نیست.');
        $cart = $this->cartFor($request);
        $line = SalesCartItem::query()->firstOrNew(['cart_id' => $cart->id, 'product_id' => $product->id]);
        $line->quantity = min(self::MAX_QTY, ($line->exists && ! $line->saved_for_later ? $line->quantity : 0) + ($data['quantity'] ?? 1));
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

    public function cartFor(Request $request): SalesCart
    {
        return SalesCart::query()->firstOrCreate(['user_id' => $request->user()->id]);
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
        $lines = $this->activeLines($cart);

        return response()->json(['data' => [
            'id' => $cart->id,
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
