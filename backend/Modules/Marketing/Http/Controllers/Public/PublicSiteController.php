<?php

namespace Modules\Marketing\Http\Controllers\Public;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Marketing\Entities\MarketingAnnouncement;
use Modules\Marketing\Entities\MarketingBlogPost;
use Modules\Marketing\Entities\MarketingPortfolioItem;
use Modules\Marketing\Entities\MarketingServiceCategory;
use Modules\Marketing\Entities\MarketingSiteSetting;
use Modules\Marketing\Entities\MarketingSolutionIndustry;
use Modules\Marketing\Entities\MarketingTestimonial;
use Modules\Marketing\Http\Controllers\Controller;

class PublicSiteController extends Controller
{
    public function site(): JsonResponse
    {
        $settings = MarketingSiteSetting::current();

        return response()->json([
            'data' => [
                'name' => $settings->site_name,
                'logo_url' => $settings->logo_url,
                'favicon_url' => $settings->favicon_url,
                'active_theme_slug' => $settings->active_theme_slug,
                'branding' => $settings->branding,
                'nav' => $settings->nav,
                'social_links' => $settings->social_links,
            ],
        ]);
    }

    public function home(): JsonResponse
    {
        $settings = MarketingSiteSetting::current();
        $blocks = $settings->home_blocks;
        if (! is_array($blocks) || $blocks === []) {
            $blocks = [
                ['type' => 'hero', 'enabled' => true],
                ['type' => 'services', 'enabled' => true],
                ['type' => 'portfolio_teaser', 'enabled' => true],
                ['type' => 'testimonials', 'enabled' => true],
                ['type' => 'announcements', 'enabled' => true],
                ['type' => 'consultation_cta', 'enabled' => true],
            ];
        }

        return response()->json([
            'data' => [
                'site' => [
                    'name' => $settings->site_name,
                    'logo_url' => $settings->logo_url,
                    'branding' => $settings->branding,
                    'active_theme_slug' => $settings->active_theme_slug,
                ],
                'blocks' => $blocks,
                'announcements' => MarketingAnnouncement::query()->active()
                    ->orderByDesc('pinned')->orderByDesc('id')->limit(5)->get(),
                'testimonials' => MarketingTestimonial::query()->published()
                    ->orderBy('sort_order')->limit(6)->get(),
                'portfolio' => MarketingPortfolioItem::query()->published()
                    ->orderByDesc('published_at')->limit(6)->get(),
                'blog' => MarketingBlogPost::query()->published()
                    ->orderByDesc('published_at')->limit(3)
                    ->get(['id', 'slug', 'title', 'excerpt', 'cover_url', 'published_at']),
                'services' => MarketingServiceCategory::query()
                    ->whereNull('parent_id')->orderBy('sort_order')->with('children')->limit(8)->get(),
                'solutions' => MarketingSolutionIndustry::query()
                    ->orderBy('sort_order')->limit(8)->get(['id', 'slug', 'name']),
            ],
        ]);
    }

    public function services(Request $request): JsonResponse
    {
        $categories = MarketingServiceCategory::query()
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->with(['children', 'services' => fn ($q) => $q->published()->orderBy('sort_order')])
            ->get();

        return response()->json(['data' => $categories]);
    }

    public function solutions(): JsonResponse
    {
        $industries = MarketingSolutionIndustry::query()
            ->orderBy('sort_order')
            ->with(['pages' => fn ($q) => $q->published()])
            ->get();

        return response()->json(['data' => $industries]);
    }
}
