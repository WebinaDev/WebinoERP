<?php

namespace Modules\Sales\Services\Storefront;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Sales\Entities\SalesCatalogItem;
use Modules\Sales\Entities\SalesCoupon;
use Modules\Sales\Entities\SalesProductReview;

/**
 * Storefront view of the sales catalog (builder shop widgets).
 * A catalog item is sold online when it is active and `meta.storefront` is true.
 * Product fields the shop needs (slug, image, sale price, category, stock, linked ids) live in `meta`.
 */
class StorefrontCatalog
{
    public const FLAT_SHIPPING = 500000;

    public const FREE_SHIPPING_OVER = 10000000;

    public function query(): Builder
    {
        return SalesCatalogItem::query()->where('status', 'active')->where('meta->storefront', true);
    }

    public function slugOf(SalesCatalogItem $item): string
    {
        $meta = (array) ($item->meta ?? []);

        return (string) (($meta['slug'] ?? null) ?: ($item->sku ?: (string) $item->id));
    }

    public function findBySlug(string $slug): SalesCatalogItem
    {
        $slug = mb_substr($slug, 0, 191);
        $row = (clone $this->query())->where('meta->slug', $slug)->first()
            ?? (clone $this->query())->where('sku', $slug)->first()
            ?? (ctype_digit($slug) ? (clone $this->query())->whereKey((int) $slug)->first() : null);
        abort_unless($row, 404);

        return $row;
    }

    /**
     * Variants live in meta.variants: [{id, name, sku, price, sale_price, stock, is_default, attributes, image_url, enabled}].
     *
     * @return list<array<string, mixed>>
     */
    public function variants(SalesCatalogItem $item): array
    {
        $meta = (array) ($item->meta ?? []);
        $out = [];
        foreach ((array) ($meta['variants'] ?? []) as $i => $v) {
            if (! is_array($v) || (array_key_exists('enabled', $v) && ! $v['enabled'])) {
                continue;
            }
            $id = preg_replace('/[^A-Za-z0-9_\-]/', '', (string) ($v['id'] ?? $v['sku'] ?? '')) ?: 'v'.($i + 1);
            $out[] = [
                'id' => mb_substr($id, 0, 40),
                'name' => (string) ($v['name'] ?? $id),
                'sku' => isset($v['sku']) ? (string) $v['sku'] : null,
                'price' => isset($v['price']) && is_numeric($v['price']) && (float) $v['price'] > 0 ? (int) round((float) $v['price']) : null,
                'sale_price' => isset($v['sale_price']) && is_numeric($v['sale_price']) ? (int) round((float) $v['sale_price']) : null,
                'sale_ends_at' => $v['sale_ends_at'] ?? null,
                'stock' => array_key_exists('stock', $v) && $v['stock'] !== null && $v['stock'] !== '' ? (int) $v['stock'] : null,
                'is_default' => (bool) ($v['is_default'] ?? false),
                'attributes' => is_array($v['attributes'] ?? null) ? $v['attributes'] : [],
                'image_url' => isset($v['image_url']) ? (string) $v['image_url'] : null,
            ];
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    public function variant(SalesCatalogItem $item, ?string $key): ?array
    {
        if ($key === null || $key === '') {
            return null;
        }
        foreach ($this->variants($item) as $v) {
            if ($v['id'] === $key) {
                return $v;
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    public function defaultVariant(SalesCatalogItem $item): ?array
    {
        $all = $this->variants($item);
        foreach ($all as $v) {
            if ($v['is_default']) {
                return $v;
            }
        }

        return null;
    }

    public function regularPrice(SalesCatalogItem $item, ?array $variant = null): int
    {
        return $variant && $variant['price'] !== null ? (int) $variant['price'] : (int) round((float) $item->price);
    }

    public function unitPrice(SalesCatalogItem $item, ?array $variant = null): int
    {
        $meta = (array) ($item->meta ?? []);
        $regular = $this->regularPrice($item, $variant);
        if ($variant) {
            $sale = $variant['sale_price'];
            $expiry = ['sale_ends_at' => $variant['sale_ends_at'] ?? ($meta['sale_ends_at'] ?? null)];
        } else {
            $sale = isset($meta['sale_price']) && is_numeric($meta['sale_price']) ? (int) round((float) $meta['sale_price']) : null;
            $expiry = $meta;
        }
        if ($sale !== null && $this->saleExpired($expiry)) {
            $sale = null;
        }

        return $sale !== null && $sale > 0 && $sale < $regular ? $sale : $regular;
    }

    /** A sale with meta.sale_ends_at in the past no longer applies (regular price comes back automatically). */
    public function saleExpired(array $meta): bool
    {
        $end = $meta['sale_ends_at'] ?? null;
        if (! is_string($end) || trim($end) === '') {
            return false;
        }
        $ts = strtotime($end);

        return $ts !== false && $ts <= time();
    }

    public function available(SalesCatalogItem $item, ?array $variant = null, int $quantity = 1): bool
    {
        if ($variant) {
            return $variant['stock'] === null || $variant['stock'] >= max(1, $quantity);
        }
        $meta = (array) ($item->meta ?? []);
        if (array_key_exists('stock', $meta) && $meta['stock'] !== null && (int) $meta['stock'] < max(1, $quantity)) {
            return false;
        }
        // A product with variants is available while at least one variant is.
        $variants = $this->variants($item);
        if ($variants !== []) {
            foreach ($variants as $v) {
                if ($v['stock'] === null || $v['stock'] >= 1) {
                    return true;
                }
            }

            return false;
        }

        return true;
    }

    /** Units left (null = unlimited). */
    public function stockLeft(SalesCatalogItem $item, ?array $variant = null): ?int
    {
        if ($variant) {
            return $variant['stock'];
        }
        $meta = (array) ($item->meta ?? []);

        return array_key_exists('stock', $meta) && $meta['stock'] !== null ? (int) $meta['stock'] : null;
    }

    /** @return array<string, mixed> */
    public function serialize(SalesCatalogItem $item): array
    {
        $meta = (array) ($item->meta ?? []);
        $regular = (int) round((float) $item->price);
        $price = $this->unitPrice($item);
        $rating = SalesProductReview::query()->where('product_id', $item->id)->where('status', 'approved');

        return [
            'id' => $item->id,
            'slug' => $this->slugOf($item),
            'name' => $item->name,
            'sku' => $item->sku,
            'description' => $item->description,
            'excerpt' => isset($meta['excerpt']) ? (string) $meta['excerpt'] : null,
            'image_url' => $meta['image_url'] ?? null,
            'cover_image_url' => $meta['cover_image_url'] ?? ($meta['image_url'] ?? null),
            'video_url' => $meta['video_url'] ?? null,
            'media' => array_values(array_map(fn ($u) => ['url' => (string) $u], array_filter((array) ($meta['gallery'] ?? []), 'is_string'))),
            'price_minor' => $regular,
            'sale_price_minor' => $price < $regular ? $price : null,
            'discounted_price_minor' => $price,
            'sale_ends_at' => $price < $regular && ! empty($meta['sale_ends_at']) && strtotime((string) $meta['sale_ends_at']) ? date(DATE_ATOM, strtotime((string) $meta['sale_ends_at'])) : null,
            'discount_percent' => $price < $regular && $regular > 0 ? (int) round(100 - $price * 100 / $regular) : null,
            // Canonical shop storage is IRR (rial). Clients convert via Money / formatMoney.
            'currency' => \App\Support\Currency::BASE,
            'currency_display' => \App\Support\Money::displayCurrency(),
            'is_available' => $this->available($item),
            'is_sold_out' => ! $this->available($item),
            'is_new' => (bool) ($meta['is_new'] ?? false),
            'is_featured' => (bool) ($meta['is_featured'] ?? false),
            'category' => isset($meta['category_slug']) ? ['slug' => (string) $meta['category_slug'], 'name' => (string) ($meta['category_name'] ?? $meta['category_slug'])] : null,
            'brands' => isset($meta['brand']) ? [['id' => 0, 'name' => (string) $meta['brand'], 'slug' => (string) $meta['brand']]] : [],
            'faqs' => is_array($meta['faqs'] ?? null) ? $meta['faqs'] : [],
            'attributes' => is_array($meta['attributes'] ?? null) ? $meta['attributes'] : [],
            'variants' => array_map(fn ($v) => [
                'id' => $v['id'],
                'name' => $v['name'],
                'sku' => $v['sku'],
                'attributes' => $v['attributes'],
                'image_url' => $v['image_url'],
                'is_default' => $v['is_default'],
                'price_minor' => $this->regularPrice($item, $v),
                'discounted_price_minor' => $this->unitPrice($item, $v),
                'sale_price_minor' => $this->unitPrice($item, $v) < $this->regularPrice($item, $v) ? $this->unitPrice($item, $v) : null,
                'stock' => $v['stock'],
                'is_available' => $this->available($item, $v),
            ], $this->variants($item)),
            'single_price' => ! (bool) app(StorefrontSettings::class)->get('pricing_enabled'),
            'rating_avg' => round((float) (clone $rating)->avg('rating'), 1),
            'reviews_count' => (clone $rating)->count(),
            'kind' => (string) ($meta['kind'] ?? 'product'),
            'course_slug' => isset($meta['course_slug']) ? (string) $meta['course_slug'] : null,
            'url' => isset($meta['url']) ? (string) $meta['url'] : null,
            'digital' => ! empty($meta['digital']) || in_array($meta['kind'] ?? '', ['course', 'digital', 'license', 'service'], true),
        ];
    }

    /** @param  Collection<int, SalesCatalogItem>  $items */
    public function categories(Collection $items): array
    {
        return $items->map(fn ($i) => (array) ($i->meta ?? []))
            ->filter(fn ($m) => ! empty($m['category_slug']))
            ->map(fn ($m) => ['slug' => (string) $m['category_slug'], 'name' => (string) ($m['category_name'] ?? $m['category_slug'])])
            ->unique('slug')->values()->all();
    }

    /**
     * Cart totals. Lines: {item, quantity, variant?}. Options: shipping_method, gift_card_code.
     *
     * @param  iterable<array{item: SalesCatalogItem, quantity: int, variant?: array<string, mixed>|null}>  $lines
     * @param  array{shipping_method?: ?string, gift_card_code?: ?string}  $options
     * @return array<string, mixed>
     */
    public function quote(iterable $lines, ?string $couponCode = null, array $options = []): array
    {
        $subtotal = 0;
        $regularTotal = 0;
        $count = 0;
        $physical = false;
        $rows = [];
        foreach ($lines as $line) {
            $variant = $line['variant'] ?? null;
            $unit = $this->unitPrice($line['item'], $variant);
            $regular = max($unit, $this->regularPrice($line['item'], $variant));
            $subtotal += $unit * $line['quantity'];
            $regularTotal += $regular * $line['quantity'];
            $count += $line['quantity'];
            $meta = (array) ($line['item']->meta ?? []);
            if (empty($meta['digital']) && ! in_array($meta['kind'] ?? '', ['course', 'digital', 'license', 'service'], true)) {
                $physical = true;
            }
            $rows[] = [
                'product_id' => $line['item']->id,
                'variant_id' => $variant['id'] ?? null,
                'variant_name' => $variant['name'] ?? null,
                'name' => $line['item']->name,
                'quantity' => (int) $line['quantity'],
                'unit_price_minor' => $unit,
                'regular_price_minor' => $regular,
                'on_sale' => $regular > $unit,
                'line_total_minor' => $unit * (int) $line['quantity'],
            ];
        }
        [$discount, $coupon, $couponError] = $this->couponDiscount($couponCode, $subtotal);
        $settings = app(StorefrontSettings::class);
        $after = max(0, $subtotal - $discount);
        $methods = array_map(function ($m) use ($count, $physical, $after) {
            $free = (int) ($m['free_over_minor'] ?? 0);
            $m['cost_minor'] = $count === 0 || ! $physical || ($free > 0 && $after >= $free) ? 0 : (int) $m['cost_minor'];

            return ['id' => $m['id'], 'title' => $m['title'], 'cost_minor' => $m['cost_minor'], 'free_over_minor' => $free];
        }, $settings->shippingMethods());
        $picked = null;
        $wanted = (string) ($options['shipping_method'] ?? '');
        foreach ($methods as $m) {
            if ($wanted !== '' && $m['id'] === $wanted) {
                $picked = $m;
            }
        }
        $methodError = $wanted !== '' && ! $picked ? 'این روش ارسال در دسترس نیست.' : null;
        $picked ??= $methods[0] ?? null;
        $shipping = $physical && $picked ? (int) $picked['cost_minor'] : 0;
        $beforeGift = $after + $shipping;

        $gift = null;
        $giftError = null;
        $giftCode = trim((string) ($options['gift_card_code'] ?? ''));
        if ($giftCode !== '' && $settings->get('gift_cards_enabled')) {
            try {
                $p = app(StorefrontGiftCards::class)->preview($giftCode, $beforeGift);
                $gift = ['code' => $p['card']->maskedCode(), 'applied_minor' => $p['applied_minor'], 'balance_minor' => $p['balance_minor'], 'remaining_minor' => $p['remaining_minor']];
            } catch (\Illuminate\Validation\ValidationException $e) {
                $giftError = (string) collect($e->errors())->flatten()->first();
            }
        } elseif ($giftCode !== '') {
            $giftError = 'کارت هدیه در این فروشگاه فعال نیست.';
        }
        $giftMinor = (int) ($gift['applied_minor'] ?? 0);
        $pricingOn = (bool) $settings->get('pricing_enabled');

        return [
            'items_count' => $count,
            'lines' => $rows,
            'regular_subtotal_minor' => $regularTotal,
            'sale_savings_minor' => max(0, $regularTotal - $subtotal),
            'subtotal_minor' => $subtotal,
            'discount_minor' => $discount,
            'shipping_minor' => $shipping,
            'shipping_methods' => $physical ? $methods : [],
            'shipping_method' => $physical && $picked ? $picked['id'] : null,
            'shipping_method_error' => $methodError,
            'total_before_gift_card_minor' => $beforeGift,
            'gift_card' => $gift,
            'gift_card_error' => $giftError,
            'gift_card_minor' => $giftMinor,
            'total_minor' => max(0, $beforeGift - $giftMinor),
            'coupon' => $coupon,
            'coupon_error' => $couponError,
            'free_shipping_over_minor' => (int) ($picked['free_over_minor'] ?? $settings->get('free_shipping_over_minor')),
            'requires_shipping' => $physical,
            'single_price' => ! $pricingOn,
            'payment_modes' => array_keys(array_filter(['cash' => (bool) $settings->get('cash_enabled'), 'installment' => $pricingOn && (bool) $settings->get('installment_enabled')])),
            'currency' => \App\Support\Currency::BASE,
            'currency_display' => \App\Support\Money::displayCurrency(),
            'currency_format' => \App\Support\Money::storefrontDisplay(),
        ];
    }

    /** @return array{0:int,1:?array<string,mixed>,2:?string} */
    public function couponDiscount(?string $code, int $subtotal): array
    {
        $code = trim((string) $code);
        if ($code === '') {
            return [0, null, null];
        }
        $coupon = SalesCoupon::query()->where('code', mb_strtoupper($code))->where('active', true)->first();
        if (! $coupon || ($coupon->expires_at && $coupon->expires_at->isPast()) || ($coupon->usage_limit !== null && $coupon->used >= $coupon->usage_limit)) {
            return [0, null, 'کد تخفیف معتبر نیست.'];
        }
        if ($subtotal < (int) $coupon->min_total) {
            return [0, null, 'مبلغ سبد برای این کد تخفیف کافی نیست.'];
        }
        $value = (float) $coupon->value;
        $discount = $coupon->type === 'fixed' ? (int) round($value) : (int) floor($subtotal * min(100, max(0, $value)) / 100);

        return [min($subtotal, $discount), ['code' => $coupon->code, 'type' => $coupon->type, 'value' => $value], null];
    }
}
