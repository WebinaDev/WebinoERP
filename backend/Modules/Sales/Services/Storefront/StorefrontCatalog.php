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

    public function unitPrice(SalesCatalogItem $item): int
    {
        $meta = (array) ($item->meta ?? []);
        $regular = (int) round((float) $item->price);
        $sale = isset($meta['sale_price']) && is_numeric($meta['sale_price']) ? (int) round((float) $meta['sale_price']) : null;
        if ($sale !== null && $this->saleExpired($meta)) {
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

    public function available(SalesCatalogItem $item): bool
    {
        $meta = (array) ($item->meta ?? []);

        return ! array_key_exists('stock', $meta) || $meta['stock'] === null || (int) $meta['stock'] > 0;
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
            'currency' => 'IRR',
            'is_available' => $this->available($item),
            'is_sold_out' => ! $this->available($item),
            'is_new' => (bool) ($meta['is_new'] ?? false),
            'is_featured' => (bool) ($meta['is_featured'] ?? false),
            'category' => isset($meta['category_slug']) ? ['slug' => (string) $meta['category_slug'], 'name' => (string) ($meta['category_name'] ?? $meta['category_slug'])] : null,
            'brands' => isset($meta['brand']) ? [['id' => 0, 'name' => (string) $meta['brand'], 'slug' => (string) $meta['brand']]] : [],
            'faqs' => is_array($meta['faqs'] ?? null) ? $meta['faqs'] : [],
            'attributes' => is_array($meta['attributes'] ?? null) ? $meta['attributes'] : [],
            'variants' => [],
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
     * @param  iterable<array{item: SalesCatalogItem, quantity: int}>  $lines
     * @return array<string, mixed>
     */
    public function quote(iterable $lines, ?string $couponCode = null): array
    {
        $subtotal = 0;
        $count = 0;
        $physical = false;
        foreach ($lines as $line) {
            $subtotal += $this->unitPrice($line['item']) * $line['quantity'];
            $count += $line['quantity'];
            $meta = (array) ($line['item']->meta ?? []);
            if (empty($meta['digital']) && ! in_array($meta['kind'] ?? '', ['course', 'digital', 'license', 'service'], true)) {
                $physical = true;
            }
        }
        [$discount, $coupon, $couponError] = $this->couponDiscount($couponCode, $subtotal);
        $settings = app(StorefrontSettings::class);
        $flat = (int) $settings->get('shipping_flat_minor');
        $freeOver = (int) $settings->get('free_shipping_over_minor');
        $shipping = $count === 0 || ! $physical || ($freeOver > 0 && ($subtotal - $discount) >= $freeOver) ? 0 : $flat;

        return [
            'items_count' => $count,
            'subtotal_minor' => $subtotal,
            'discount_minor' => $discount,
            'shipping_minor' => $shipping,
            'total_minor' => max(0, $subtotal - $discount) + $shipping,
            'coupon' => $coupon,
            'coupon_error' => $couponError,
            'free_shipping_over_minor' => $freeOver,
            'requires_shipping' => $physical,
            'currency' => 'IRR',
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
