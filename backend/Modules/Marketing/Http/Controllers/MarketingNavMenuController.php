<?php

namespace Modules\Marketing\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Marketing\Entities\MarketingBlogPost;
use Modules\Marketing\Entities\MarketingNavMenu as NavMenu;
use Modules\Marketing\Entities\MarketingPage;

/**
 * «منوها» — WordPress-style navigation menus. Admin CRUD (also used inline by the page-builder nav widget)
 * and a public endpoint the storefront header/footer/mobile nav read (falls back to theme defaults when empty).
 */
class MarketingNavMenuController extends Controller
{
    private const TYPES = ['custom', 'page', 'category', 'brand', 'product', 'post'];

    private const MAX_ITEMS = 400;

    private const MAX_DEPTH = 3;

    private function forget(): void
    {
        Cache::forget('marketing:nav-menus:public');
    }

    public function index(Request $request): JsonResponse
    {
        $rows = NavMenu::query()->orderBy('id')->get();

        return response()->json(['data' => $rows, 'meta' => ['locations' => NavMenu::LOCATIONS]]);
    }

    public function show(Request $request, int $menu): JsonResponse
    {
        return response()->json(['data' => NavMenu::query()->findOrFail($menu)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request, null);
        $row = NavMenu::create($data);
        $this->claimLocations($row);
        $this->forget();

        return response()->json(['data' => $row->fresh()], 201);
    }

    public function update(Request $request, int $menu): JsonResponse
    {
        $row = NavMenu::query()->findOrFail($menu);
        $row->fill($this->validated($request, $row))->save();
        $this->claimLocations($row);
        $this->forget();

        return response()->json(['data' => $row->fresh()]);
    }

    public function destroy(Request $request, int $menu): JsonResponse
    {
        NavMenu::query()->findOrFail($menu)->delete();
        $this->forget();

        return response()->json(['data' => ['deleted' => true]]);
    }

    /** Link picker: pages, product categories, brands, products, blog posts (id, label, slug). */
    public function sources(Request $request): JsonResponse
    {
        $type = (string) $request->query('type', 'page');
        $q = trim((string) $request->query('q', ''));
        $like = fn ($query, string $col) => $q === '' ? $query : $query->where($col, 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%');
        // ERP: marketing pages + blog posts. Shop categories/brands/products live in the tenant dashboards.
        $rows = match ($type) {
            'post' => $like(MarketingBlogPost::query(), 'title')->latest('id')->limit(30)->get(['id', 'title as label', 'slug']),
            'page' => $like(MarketingPage::query(), 'title_fa')->latest('id')->limit(50)->get(['id', 'title_fa as label', 'slug']),
            default => collect(),
        };

        return response()->json(['data' => $rows]);
    }

    /** Mega-menu auto-fill needs shop categories, which the ERP marketing site does not have. */
    public function fromCategories(Request $request): JsonResponse
    {
        return response()->json(['data' => []]);
    }

    /** Public: { menus: {slug: items}, locations: {location: items} } — hidden items stripped server-side. */
    public function publicIndex(Request $request): JsonResponse
    {
        $data = Cache::remember('marketing:nav-menus:public', 300, function () {
            $menus = [];
            $locations = [];
            foreach (NavMenu::query()->orderBy('id')->get() as $m) {
                $items = $this->publicItems((array) ($m->items ?? []));
                $menus[$m->slug] = ['id' => $m->id, 'name' => $m->name, 'items' => $items];
                foreach ((array) ($m->locations ?? []) as $loc) {
                    $locations[$loc] ??= $m->slug;
                }
            }

            return ['menus' => $menus, 'locations' => $locations];
        });

        return response()->json(['data' => $data]);
    }

    /** @param  array<int, mixed>  $items */
    private function publicItems(array $items): array
    {
        $out = [];
        foreach ($items as $it) {
            if (! is_array($it) || ($it['visibility'] ?? 'all') === 'hidden') {
                continue;
            }
            $it['children'] = $this->publicItems((array) ($it['children'] ?? []));
            $out[] = $it;
        }

        return $out;
    }

    private function validated(Request $request, ?NavMenu $row): array
    {
        $v = $request->validate([
            'name' => [$row ? 'sometimes' : 'required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120', 'regex:/^[\p{L}\p{N}_-]+$/u',
                Rule::unique('marketing_nav_menus', 'slug')->ignore($row?->id)],
            'items' => ['nullable', 'array', 'max:'.self::MAX_ITEMS],
            'locations' => ['nullable', 'array'],
            'locations.*' => [Rule::in(NavMenu::LOCATIONS)],
        ]);
        $out = [];
        if (array_key_exists('name', $v)) {
            $out['name'] = trim($v['name']);
        }
        if (! empty($v['slug'])) {
            $out['slug'] = $v['slug'];
        } elseif (! $row) {
            $base = Str::slug($v['name']) ?: 'menu';
            $slug = $base;
            for ($i = 2; NavMenu::query()->where('slug', $slug)->exists(); $i++) {
                $slug = $base.'-'.$i;
            }
            $out['slug'] = $slug;
        }
        if ($request->has('items')) {
            $count = 0;
            $out['items'] = $this->cleanItems((array) ($v['items'] ?? []), 1, $count);
        }
        if ($request->has('locations')) {
            $out['locations'] = array_values(array_unique($v['locations'] ?? []));
        }

        return $out;
    }

    /** Whitelist item keys, clamp depth/count; custom URLs must be relative paths, http(s), tel: or mailto:. */
    private function cleanItems(array $items, int $depth, int &$count): array
    {
        $out = [];
        foreach ($items as $it) {
            if (! is_array($it) || ++$count > self::MAX_ITEMS) {
                continue;
            }
            $type = in_array($it['type'] ?? '', self::TYPES, true) ? $it['type'] : 'custom';
            $url = trim((string) ($it['url'] ?? ''));
            if ($url !== '' && ! preg_match('#^(/|https?://|tel:|mailto:|\#)#i', $url)) {
                $url = '/'.ltrim($url, '/');
            }
            $vis = in_array($it['visibility'] ?? 'all', ['all', 'guest', 'logged_in', 'desktop', 'mobile', 'hidden'], true) ? ($it['visibility'] ?? 'all') : 'all';
            $out[] = array_filter([
                'id' => substr((string) ($it['id'] ?? Str::random(8)), 0, 40),
                'label' => mb_substr(trim((string) ($it['label'] ?? '')), 0, 120),
                'type' => $type,
                'ref' => isset($it['ref']) ? mb_substr((string) $it['ref'], 0, 255) : null,
                'ref_id' => isset($it['ref_id']) && is_numeric($it['ref_id']) ? (int) $it['ref_id'] : null,
                'url' => $url !== '' ? mb_substr($url, 0, 1024) : null,
                'icon' => isset($it['icon']) && $it['icon'] !== '' ? mb_substr((string) $it['icon'], 0, 1024) : null,
                'badge' => isset($it['badge']) && $it['badge'] !== '' ? mb_substr((string) $it['badge'], 0, 30) : null,
                'badge_color' => isset($it['badge_color']) && preg_match('/^#[0-9a-f]{3,8}$/i', (string) $it['badge_color']) ? $it['badge_color'] : null,
                'new_tab' => ! empty($it['new_tab']) ? true : null,
                'mega' => ! empty($it['mega']) ? true : null,
                'visibility' => $vis !== 'all' ? $vis : null,
                'children' => $depth < self::MAX_DEPTH ? $this->cleanItems((array) ($it['children'] ?? []), $depth + 1, $count) : [],
            ], fn ($val) => $val !== null);
        }

        return $out;
    }

    /** A location belongs to one menu: assigning it here removes it from the others. */
    private function claimLocations(NavMenu $row): void
    {
        $mine = (array) ($row->locations ?? []);
        if ($mine === []) {
            return;
        }
        NavMenu::query()->where('id', '!=', $row->id)->get()->each(function (NavMenu $other) use ($mine) {
            $left = array_values(array_diff((array) ($other->locations ?? []), $mine));
            if ($left !== (array) ($other->locations ?? [])) {
                $other->locations = $left;
                $other->save();
            }
        });
    }
}
