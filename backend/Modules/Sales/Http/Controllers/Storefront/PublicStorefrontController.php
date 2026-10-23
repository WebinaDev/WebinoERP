<?php

namespace Modules\Sales\Http\Controllers\Storefront;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Modules\Sales\Entities\SalesCatalogItem;
use Modules\Sales\Entities\SalesProductQuestion;
use Modules\Sales\Entities\SalesProductReview;
use Modules\Sales\Entities\SalesStorefrontOrder;
use Modules\Sales\Http\Controllers\Controller;
use Modules\Sales\Services\Storefront\StorefrontCatalog;

/** Public shop API used by the builder storefront widgets (same paths/shapes as WebinoDashboard). */
class PublicStorefrontController extends Controller
{
    public function __construct(private StorefrontCatalog $catalog) {}

    public function index(Request $request): JsonResponse
    {
        $all = (clone $this->catalog->query())->get();
        $query = $this->catalog->query();
        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $like = '%'.addcslashes(mb_substr($q, 0, 80), '%_\\').'%';
            $query->where(fn ($b) => $b->where('name', 'like', $like)->orWhere('description', 'like', $like)->orWhere('sku', 'like', $like));
        }
        if ($request->filled('category')) {
            $query->where('meta->category_slug', (string) $request->query('category'));
        }
        if ($request->filled('brand')) {
            $query->where('meta->brand', (string) $request->query('brand'));
        }
        if ($request->boolean('featured')) {
            $query->where('meta->is_featured', true);
        }
        $ids = array_filter(array_map('intval', explode(',', (string) $request->query('ids', ''))));
        if ($ids !== []) {
            $query->whereIn('id', array_slice($ids, 0, 48));
        }
        $sort = (string) $request->query('sort', 'newest');
        $perPage = max(1, min(96, (int) $request->query('per_page', 12)));
        $page = max(1, (int) $request->query('page', 1));
        // Builder `archive-filters` / Loop Grid: price, sale and stock live on the effective (sale-aware) price
        // and JSON meta, so these filters run on the matched collection (storefront catalogs are small).
        $archive = $request->hasAny(['min_price', 'max_price', 'on_sale', 'in_stock', 'tag', 'attr']) || in_array($sort, ['popular', 'best_selling'], true);
        if ($archive) {
            $rows = $this->archiveFilter($query->get(), $request, $sort);
            $total = $rows->count();
            $items = $rows->slice(($page - 1) * $perPage, $perPage)->map(fn ($i) => $this->catalog->serialize($i))->values();
        } else {
            match ($sort) {
                'price_asc', 'price' => $query->orderBy('price'),
                'price_desc' => $query->orderByDesc('price'),
                'name' => $query->orderBy('name'),
                default => $query->orderByDesc('id'),
            };
            $total = (clone $query)->count();
            $items = $query->skip(($page - 1) * $perPage)->take($perPage)->get()->map(fn ($i) => $this->catalog->serialize($i))->values();
        }

        return response()->json(['data' => [
            'categories' => $this->catalog->categories($all),
            'brands' => $all->map(fn ($i) => $i->meta['brand'] ?? null)->filter()->unique()->values()->map(fn ($b) => ['id' => 0, 'name' => $b, 'slug' => $b]),
            'items' => $items,
            'currency_display' => ['currency' => 'IRT', 'currency_position' => 'after', 'thousand_separator' => ',', 'decimal_separator' => '.', 'price_decimals' => 0],
            'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total],
            'query' => $q !== '' ? $q : null,
        ]])->header('Cache-Control', 'no-cache, must-revalidate');
    }

    public function show(string $slug): JsonResponse
    {
        $item = $this->catalog->findBySlug($slug);
        $data = $this->catalog->serialize($item);
        $data['likes_count'] = 0;
        $data['return_notice'] = $item->meta['return_notice'] ?? null;

        return response()->json(['data' => $data])->header('Cache-Control', 'no-cache, must-revalidate');
    }

    public function linked(Request $request, string $slug): JsonResponse
    {
        $type = (string) $request->query('type', 'upsell');
        $key = ['upsell' => 'upsell_ids', 'cross_sell' => 'cross_sell_ids', 'related' => 'related_ids'][$type] ?? null;
        abort_if($key === null, 422, 'Invalid type');
        $item = $this->catalog->findBySlug($slug);
        $ids = array_slice(array_values(array_filter(array_map('intval', (array) ($item->meta[$key] ?? [])))), 0, 12);
        if ($ids === [] && $type === 'related' && ! empty($item->meta['category_slug'])) {
            $ids = (clone $this->catalog->query())->where('meta->category_slug', $item->meta['category_slug'])->whereKeyNot($item->id)->limit(8)->pluck('id')->all();
        }
        $rows = $ids === [] ? collect() : (clone $this->catalog->query())->whereIn('id', $ids)->get()
            ->sortBy(fn ($p) => array_search($p->id, $ids, true))->values()->map(fn ($p) => $this->catalog->serialize($p));

        return response()->json(['data' => ['items' => $rows->all()]]);
    }

    public function reviews(string $slug): JsonResponse
    {
        $item = $this->catalog->findBySlug($slug);
        $rows = SalesProductReview::query()->where('product_id', $item->id)->where('status', 'approved')->orderByDesc('id')->limit(50)->get();

        return response()->json(['data' => [
            'items' => $rows->map(fn ($r) => ['id' => $r->id, 'author_name' => $r->author_name ?: 'کاربر', 'rating' => $r->rating, 'body' => $r->body, 'likes' => $r->likes, 'created_at' => $r->created_at?->toIso8601String()])->values(),
            'average' => round((float) $rows->avg('rating'), 1),
            'total' => $rows->count(),
        ]]);
    }

    public function storeReview(Request $request, string $slug): JsonResponse
    {
        $item = $this->catalog->findBySlug($slug);
        $data = $request->validate(['rating' => 'required|integer|min:1|max:5', 'body' => 'nullable|string|max:2000', 'author_name' => 'nullable|string|max:80']);
        $row = SalesProductReview::query()->create([
            'product_id' => $item->id,
            'user_id' => $request->user('sanctum')?->id,
            'author_name' => isset($data['author_name']) ? strip_tags($data['author_name']) : $request->user('sanctum')?->name,
            'rating' => $data['rating'],
            'body' => isset($data['body']) ? strip_tags($data['body']) : null,
            'status' => 'pending',
        ]);

        return response()->json(['data' => ['id' => $row->id, 'status' => $row->status]], 201);
    }

    public function react(Request $request, int $review): JsonResponse
    {
        $row = SalesProductReview::query()->where('status', 'approved')->findOrFail($review);
        $key = 'sf:review-like:'.$row->id.':'.sha1((string) $request->ip());
        if (Cache::add($key, 1, now()->addDay())) {
            $row->increment('likes');
        }

        return response()->json(['data' => ['likes' => $row->fresh()->likes]]);
    }

    public function report(Request $request, int $review): JsonResponse
    {
        $row = SalesProductReview::query()->findOrFail($review);
        if (Cache::add('sf:review-report:'.$row->id.':'.sha1((string) $request->ip()), 1, now()->addDay())) {
            $row->increment('reports');
            if ($row->reports >= 5) {
                $row->update(['status' => 'pending']);
            }
        }

        return response()->json(['data' => ['ok' => true]]);
    }

    public function questions(string $slug): JsonResponse
    {
        $item = $this->catalog->findBySlug($slug);
        $rows = SalesProductQuestion::query()->where('product_id', $item->id)->where('status', 'answered')->orderByDesc('id')->limit(50)->get();

        return response()->json(['data' => ['items' => $rows->map(fn ($q) => ['id' => $q->id, 'body' => $q->body, 'answer' => $q->answer, 'created_at' => $q->created_at?->toIso8601String()])->values()]]);
    }

    public function compare(Request $request): JsonResponse
    {
        $ids = $this->compareIds($request);
        $items = $ids === [] ? collect() : (clone $this->catalog->query())->whereIn('id', $ids)->get()->map(fn ($i) => $this->catalog->serialize($i))->values();

        return response()->json(['data' => ['items' => $items, 'ids' => $ids]]);
    }

    public function compareAdd(Request $request, int $productId): JsonResponse
    {
        (clone $this->catalog->query())->findOrFail($productId);
        $ids = array_values(array_unique(array_merge($this->compareIds($request), [$productId])));
        $ids = array_slice($ids, -4);
        Cache::put($this->compareKey($request), $ids, now()->addDays(7));

        return response()->json(['data' => ['ids' => $ids]]);
    }

    public function compareRemove(Request $request, int $productId): JsonResponse
    {
        $ids = array_values(array_filter($this->compareIds($request), fn ($id) => $id !== $productId));
        Cache::put($this->compareKey($request), $ids, now()->addDays(7));

        return response()->json(['data' => ['ids' => $ids]]);
    }

    public function stories(): JsonResponse
    {
        $rows = (clone $this->catalog->query())->whereNotNull('meta->story_video_url')->orderByDesc('id')->limit(20)->get();

        return response()->json(['data' => $rows->map(fn (SalesCatalogItem $i) => [
            'id' => $i->id,
            'title' => $i->name,
            'video_url' => (string) ($i->meta['story_video_url'] ?? ''),
            'cover_url' => $i->meta['image_url'] ?? null,
            'product' => $this->catalog->serialize($i),
        ])->values()]);
    }

    public function previewCoupon(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => 'required|string|max:40', 'subtotal_minor' => 'nullable|integer|min:0']);
        [$discount, $coupon, $error] = $this->catalog->couponDiscount($data['code'], (int) ($data['subtotal_minor'] ?? 0));
        if ($error) {
            return response()->json(['message' => $error], 422);
        }

        return response()->json(['data' => ['coupon' => $coupon, 'discount_minor' => $discount]]);
    }

    public function trackOrder(Request $request): JsonResponse
    {
        $data = $request->validate(['number' => 'required|string|max:30', 'phone' => 'required|string|max:30']);
        $order = SalesStorefrontOrder::query()->where('number', $data['number'])->first();
        abort_unless($order && $order->phone && preg_replace('/\D/', '', $order->phone) === preg_replace('/\D/', '', $data['phone']), 404);

        return response()->json(['data' => ['number' => $order->number, 'status' => $order->status, 'tracking_code' => $order->tracking_code, 'total_minor' => (int) $order->total, 'created_at' => $order->created_at?->toIso8601String()]]);
    }

    /** @return list<int> */
    private function compareIds(Request $request): array
    {
        return array_values(array_map('intval', (array) Cache::get($this->compareKey($request), [])));
    }

    private function compareKey(Request $request): string
    {
        $user = $request->user('sanctum');

        return 'sf:compare:'.($user ? 'u'.$user->id : 'ip'.sha1((string) $request->ip().'|'.$request->userAgent()));
    }

    /**
     * Archive filters (same query keys as WebinoDashboard): min_price/max_price (minor units, effective price),
     * on_sale, in_stock, tag (comma slugs from meta.tags), attr ("attr:term" pairs from meta.attributes), sort.
     *
     * @param  \Illuminate\Support\Collection<int, SalesCatalogItem>  $rows
     * @return \Illuminate\Support\Collection<int, SalesCatalogItem>
     */
    private function archiveFilter($rows, Request $request, string $sort)
    {
        $min = $request->filled('min_price') ? max(0, (int) $request->query('min_price')) : null;
        $max = $request->filled('max_price') ? max(0, (int) $request->query('max_price')) : null;
        if ($min !== null && $max !== null && $max > 0 && $min > $max) {
            [$min, $max] = [$max, $min];
        }
        $list = fn (string $key) => array_slice(array_values(array_filter(array_map(fn ($v) => mb_strtolower(trim($v)), explode(',', (string) $request->query($key, ''))))), 0, 10);
        $tags = $list('tag');
        $pairs = array_values(array_filter(array_map(fn ($p) => explode(':', $p, 2), $list('attr')), fn ($p) => count($p) === 2 && $p[0] !== '' && $p[1] !== ''));
        $onSale = $request->boolean('on_sale');
        $inStock = $request->boolean('in_stock');

        $rows = $rows->filter(function (SalesCatalogItem $item) use ($min, $max, $tags, $pairs, $onSale, $inStock) {
            $meta = (array) ($item->meta ?? []);
            $price = $this->catalog->unitPrice($item);
            if ($min !== null && $min > 0 && $price < $min) {
                return false;
            }
            if ($max !== null && $max > 0 && $price > $max) {
                return false;
            }
            if ($onSale && $price >= (int) round((float) $item->price)) {
                return false;
            }
            if ($inStock && ! $this->catalog->available($item)) {
                return false;
            }
            if ($tags !== []) {
                $have = array_map(fn ($t) => mb_strtolower(is_array($t) ? (string) ($t['slug'] ?? $t['name'] ?? '') : (string) $t), (array) ($meta['tags'] ?? []));
                if (array_intersect($tags, $have) === []) {
                    return false;
                }
            }
            foreach ($pairs as [$attr, $term]) {
                if (! $this->hasAttributeTerm((array) ($meta['attributes'] ?? []), $attr, $term)) {
                    return false;
                }
            }

            return true;
        });

        $sorted = match ($sort) {
            'price_asc', 'price' => $rows->sortBy(fn ($i) => $this->catalog->unitPrice($i)),
            'price_desc' => $rows->sortByDesc(fn ($i) => $this->catalog->unitPrice($i)),
            'name' => $rows->sortBy('name'),
            'popular' => $rows->sortByDesc(fn ($i) => (int) (((array) $i->meta)['views_count'] ?? 0)),
            'best_selling' => $rows->sortByDesc(fn ($i) => (int) (((array) $i->meta)['sales_count'] ?? 0)),
            default => $rows->sortByDesc('id'),
        };

        return $sorted->values();
    }

    /** meta.attributes accepts {slug|name, value|values|terms} rows or a {name: value(s)} map. */
    private function hasAttributeTerm(array $attributes, string $attr, string $term): bool
    {
        $norm = fn ($v) => mb_strtolower(trim(is_array($v) ? (string) ($v['slug'] ?? $v['name'] ?? $v['value'] ?? '') : (string) $v));
        foreach ($attributes as $key => $row) {
            if (is_array($row) && ! array_is_list($row) && (isset($row['slug']) || isset($row['name']))) {
                $name = $norm($row['slug'] ?? $row['name']);
                $values = $row['values'] ?? $row['terms'] ?? $row['value'] ?? [];
            } else {
                $name = $norm((string) $key);
                $values = $row;
            }
            if ($name !== $attr) {
                continue;
            }
            $values = is_array($values) ? $values : explode(',', (string) $values);
            if (in_array($term, array_map($norm, $values), true)) {
                return true;
            }
        }

        return false;
    }
}
