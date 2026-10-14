<?php

namespace Modules\Marketing\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Marketing\Entities\MarketingSiteSetting;

/**
 * SEO for routes that have no content row of their own (home, /services, /team, /testimonials, /products, …).
 * Stored in site settings branding.seo_routes keyed by path; other branding keys are kept.
 */
class MarketingSeoController extends Controller
{
    public const ROUTES = ['/', '/about', '/contact', '/services', '/solutions', '/products', '/team', '/testimonials'];

    public function index(): JsonResponse
    {
        $saved = (array) (MarketingSiteSetting::current()->branding['seo_routes'] ?? []);

        return response()->json(['data' => array_map(fn ($p, $i) => ['id' => $i + 1, 'path' => $p] + (array) ($saved[$p] ?? []), self::ROUTES, array_keys(self::ROUTES)),
            'meta' => ['organization' => MarketingSiteSetting::current()->branding['organization'] ?? (object) []]]);
    }

    public function update(Request $request, ?int $id = null): JsonResponse
    {
        if ($id !== null) {
            abort_unless(isset(self::ROUTES[$id - 1]), 404);
            $request->merge(['path' => self::ROUTES[$id - 1]]);
        }
        $data = $request->validate([
            'path' => 'required|string|in:'.implode(',', self::ROUTES),
            'title' => 'nullable|string|max:120',
            'description' => 'nullable|string|max:320',
            'og_image' => 'nullable|string|max:500',
            'canonical' => 'nullable|string|max:500',
            'noindex' => 'nullable|boolean',
        ]);
        $settings = MarketingSiteSetting::current();
        $branding = is_array($settings->branding) ? $settings->branding : [];
        $routes = (array) ($branding['seo_routes'] ?? []);
        $routes[$data['path']] = array_filter(array_diff_key($data, ['path' => 1]), fn ($v) => $v !== null && $v !== '' && $v !== false);
        $branding['seo_routes'] = $routes;
        $settings->branding = $branding;
        $settings->save();

        return response()->json(['data' => ['id' => array_search($data['path'], self::ROUTES, true) + 1, 'path' => $data['path']] + $routes[$data['path']]]);
    }
}
