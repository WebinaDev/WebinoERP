<?php

namespace Modules\Marketing\Http\Controllers\Public;

use Illuminate\Http\JsonResponse;
use Modules\Marketing\Http\Controllers\Controller;
use Modules\Marketing\Entities\MarketingAcademyCourse;
use Modules\Marketing\Entities\MarketingAnnouncement;
use Modules\Marketing\Entities\MarketingBlogPost;
use Modules\Marketing\Entities\MarketingMagazinePost;
use Modules\Marketing\Entities\MarketingPage;
use Modules\Marketing\Entities\MarketingPortfolioItem;
use Modules\Marketing\Entities\MarketingService;
use Modules\Marketing\Entities\MarketingSiteSetting;
use Modules\Marketing\Entities\MarketingSolutionIndustry;
use Modules\Marketing\Entities\MarketingSolutionPage;

/** Every published, indexable public URL (paths only; the site adds its origin). noindex items are left out. */
class PublicSitemapController extends Controller
{
    public function index(): JsonResponse
    {
        $out = [];
        $add = function (string $path, $updated, $seo = null) use (&$out) {
            if (is_array($seo) && ! empty($seo['noindex'])) {
                return;
            }
            $out[$path] = ['path' => $path, 'lastmod' => $updated ? \Illuminate\Support\Carbon::parse($updated)->toAtomString() : null];
        };
        $static = (array) (MarketingSiteSetting::current()->branding['seo_routes'] ?? []);
        foreach (\Modules\Marketing\Http\Controllers\MarketingSeoController::ROUTES as $p) {
            $add($p, null, $static[$p] ?? null);
        }
        foreach (MarketingPage::query()->where('published', true)->get(['slug', 'meta', 'updated_at', 'builder_published']) as $p) {
            $path = $p->slug === 'home' ? '/' : (is_array($p->builder_published) ? '/'.$p->slug : '/pages/'.$p->slug);
            $add($path, $p->updated_at, is_array($p->meta) ? ($p->meta['seo'] ?? null) : null);
        }
        foreach (MarketingBlogPost::query()->published()->get(['slug', 'seo', 'updated_at']) as $r) {
            $add('/blog/'.$r->slug, $r->updated_at, $r->seo);
        }
        foreach (MarketingMagazinePost::query()->published()->get(['slug', 'seo', 'updated_at']) as $r) {
            $add('/magazine/'.$r->slug, $r->updated_at, $r->seo);
        }
        foreach (MarketingAcademyCourse::query()->published()->get(['slug', 'seo', 'updated_at']) as $r) {
            $add('/academy/'.$r->slug, $r->updated_at, $r->seo);
        }
        foreach (MarketingAnnouncement::query()->where('published', true)->get(['id', 'slug', 'seo', 'updated_at']) as $r) {
            $add('/announcements/'.($r->slug ?: $r->id), $r->updated_at, $r->seo);
        }
        foreach (MarketingPortfolioItem::query()->published()->get(['slug', 'seo', 'updated_at']) as $r) {
            $add('/portfolio/'.$r->slug, $r->updated_at, $r->seo);
        }
        foreach (MarketingService::query()->published()->get(['slug', 'seo', 'updated_at']) as $r) {
            $add('/services/'.$r->slug, $r->updated_at, $r->seo);
        }
        $industries = MarketingSolutionIndustry::query()->get(['id', 'slug', 'seo', 'updated_at'])->keyBy('id');
        foreach ($industries as $i) {
            $add('/solutions/'.$i->slug, $i->updated_at, $i->seo);
        }
        foreach (MarketingSolutionPage::query()->published()->get(['industry_id', 'slug', 'seo', 'updated_at']) as $r) {
            if ($ind = $industries->get($r->industry_id)) {
                $add('/solutions/'.$ind->slug.'/'.$r->slug, $r->updated_at, $r->seo);
            }
        }

        return response()->json(['data' => array_values($out)]);
    }
}
