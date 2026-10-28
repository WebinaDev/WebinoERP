<?php

namespace Modules\Sales\Services\Storefront;

use App\Support\Currency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Sales\Entities\SalesCatalogItem;

/**
 * Seeds shop catalog items for things sold on the site: builder product/package pages (sale-product-hero)
 * and academy courses (`course-{slug}`). Create-only: once an item exists, the shop admin owns its price/status.
 */
class StorefrontSellables
{
    /** Default course price (IRR) until the admin sets one in the shop catalog. */
    public const DEFAULT_COURSE_PRICE = 25000000;

    /** @return array{created: list<string>, existing: list<string>} */
    public function sync(): array
    {
        $out = ['created' => [], 'existing' => []];
        foreach ($this->builderProducts() as $row) {
            $this->ensure($row, $out);
        }
        if (Schema::hasTable('marketing_academy_courses')) {
            foreach (DB::table('marketing_academy_courses')->get() as $course) {
                $this->ensureCourse($course, $out);
            }
        }

        return $out;
    }

    /** @param  object|array<string,mixed>  $course */
    public function ensureCourse(object|array $course, ?array &$out = null): void
    {
        $course = (object) $course;
        if (! empty($course->is_free) || empty($course->slug)) {
            return;
        }
        $out ??= ['created' => [], 'existing' => []];
        $this->ensure([
            'slug' => 'course-'.$course->slug,
            'name' => (string) $course->title,
            'price' => self::DEFAULT_COURSE_PRICE,
            'description' => mb_substr(strip_tags((string) ($course->description ?? '')), 0, 2000),
            'meta' => [
                'kind' => 'course', 'digital' => true, 'single' => true, 'course_slug' => (string) $course->slug,
                'image_url' => $course->cover_url ?? null, 'url' => '/academy/'.$course->slug,
                'category_slug' => 'academy', 'category_name' => 'دوره‌های آموزشی',
            ],
        ], $out);
    }

    /** @param  array<string,mixed>  $row */
    private function ensure(array $row, array &$out): void
    {
        $exists = SalesCatalogItem::query()->where('meta->slug', $row['slug'])->orWhere('sku', $row['slug'])->exists();
        if ($exists) {
            $out['existing'][] = $row['slug'];

            return;
        }
        SalesCatalogItem::query()->create([
            'name' => mb_substr($row['name'], 0, 255),
            'sku' => $row['slug'],
            'price' => $row['price'],
            'description' => $row['description'] ?? null,
            'status' => 'active',
            'type' => 'product',
            'meta' => array_merge(['storefront' => true, 'slug' => $row['slug']], $row['meta']),
        ]);
        $out['created'][] = $row['slug'];
    }

    /** @return list<array<string,mixed>> */
    private function builderProducts(): array
    {
        if (! Schema::hasTable('marketing_pages') || ! Schema::hasColumn('marketing_pages', 'builder_published')) {
            return [];
        }
        $rows = [];
        $pages = DB::table('marketing_pages')->where('builder_published', 'like', '%sale-product-hero%')->get(['slug', 'builder_published']);
        foreach ($pages as $page) {
            $doc = json_decode((string) $page->builder_published, true);
            $this->walk($doc, function (array $w) use ($page, &$rows) {
                if (($w['type'] ?? '') !== 'sale-product-hero') {
                    return;
                }
                $p = (array) ($w['props'] ?? []);
                $slug = trim((string) ($p['slug'] ?? ''));
                if ($slug === '') {
                    return;
                }
                $toman = (int) preg_replace('/\D/', '', (string) ($p['price'] ?? '0'));
                $old = (int) preg_replace('/\D/', '', (string) ($p['oldPrice'] ?? '0'));
                // Builder gallery lines are «url|alt».
                $images = array_values(array_filter(array_map(fn ($l) => trim(explode('|', $l)[0]), preg_split('/\R/u', (string) ($p['images'] ?? '')) ?: [])));
                $isPackage = str_starts_with((string) $page->slug, 'package-');
                $rows[] = [
                    'slug' => $slug,
                    'name' => trim(explode('؛', (string) ($p['title'] ?? $slug))[0]),
                    // Builder prices are toman; the shop stores IRR. Old price becomes the regular price, current one the sale price.
                    'price' => Currency::toRialInt($old > $toman ? $old : $toman, 'IRT'),
                    'description' => (string) ($p['subtitle'] ?? ''),
                    'meta' => array_filter([
                        'kind' => $isPackage ? 'package' : 'license', 'digital' => true,
                        'sale_price' => $old > $toman ? Currency::toRialInt($toman, 'IRT') : null,
                        'image_url' => $images[0] ?? null, 'gallery' => $images,
                        'category_slug' => $isPackage ? 'packages' : 'products',
                        'category_name' => (string) ($p['category'] ?? ($isPackage ? 'پکیج‌ها' : 'محصولات')),
                        'url' => $isPackage ? '/products/packages/'.$slug : '/products/'.$slug,
                    ], fn ($v) => $v !== null && $v !== []),
                ];
            });
        }

        return $rows;
    }

    private function walk(mixed $node, callable $cb): void
    {
        if (! is_array($node)) {
            return;
        }
        if (isset($node['type']) && is_string($node['type'])) {
            $cb($node);
        }
        foreach ($node as $child) {
            $this->walk($child, $cb);
        }
    }
}
