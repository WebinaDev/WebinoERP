<?php

namespace Modules\Marketing\Http\Controllers\Public;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Marketing\Entities\MarketingAcademyCourse;
use Modules\Marketing\Entities\MarketingAnnouncement;
use Modules\Marketing\Entities\MarketingBlogPost;
use Modules\Marketing\Entities\MarketingDownload;
use Modules\Marketing\Entities\MarketingFaqItem;
use Modules\Marketing\Entities\MarketingMagazinePost;
use Modules\Marketing\Entities\MarketingPage;
use Modules\Marketing\Entities\MarketingPortfolioItem;
use Modules\Marketing\Entities\MarketingService;
use Modules\Marketing\Entities\MarketingSolutionIndustry;
use Modules\Marketing\Entities\MarketingSolutionPage;
use Modules\Marketing\Entities\MarketingTeamMember;
use Modules\Marketing\Entities\MarketingTestimonial;
use Modules\Marketing\Http\Controllers\Controller;

class PublicContentController extends Controller
{
    public function page(string $slug): JsonResponse
    {
        $page = MarketingPage::query()->published()->where('slug', $slug)->firstOrFail();

        return response()->json(['data' => $page]);
    }

    public function blog(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 12), 50);
        $posts = MarketingBlogPost::query()->published()
            ->with('category')
            ->orderByDesc('published_at')
            ->paginate($perPage);

        return response()->json([
            'data' => $posts->items(),
            'meta' => ['current_page' => $posts->currentPage(), 'last_page' => $posts->lastPage(), 'total' => $posts->total()],
        ]);
    }

    public function blogShow(string $slug): JsonResponse
    {
        $post = MarketingBlogPost::query()->published()->where('slug', $slug)->with('category')->firstOrFail();

        return response()->json(['data' => $post]);
    }

    public function magazine(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 12), 50);
        $posts = MarketingMagazinePost::query()->published()
            ->orderByDesc('published_at')
            ->paginate($perPage);

        return response()->json([
            'data' => $posts->items(),
            'meta' => ['current_page' => $posts->currentPage(), 'last_page' => $posts->lastPage(), 'total' => $posts->total()],
        ]);
    }

    public function magazineShow(string $slug): JsonResponse
    {
        $post = MarketingMagazinePost::query()->published()->where('slug', $slug)->firstOrFail();

        return response()->json(['data' => $post]);
    }

    public function academy(): JsonResponse
    {
        $courses = MarketingAcademyCourse::query()->published()->orderBy('sort_order')->get();

        return response()->json(['data' => $courses]);
    }

    public function academyShow(string $slug): JsonResponse
    {
        $course = MarketingAcademyCourse::query()->published()
            ->where('slug', $slug)
            ->with(['lessons' => fn ($q) => $q->where('published', true)->orderBy('sort_order')])
            ->firstOrFail();

        return response()->json(['data' => $course]);
    }

    public function portfolio(Request $request): JsonResponse
    {
        $query = MarketingPortfolioItem::query()->published()->with(['service', 'industry']);

        if ($request->filled('service')) {
            $query->whereHas('service', fn ($q) => $q->where('slug', $request->get('service')));
        }
        if ($request->filled('industry')) {
            $query->whereHas('industry', fn ($q) => $q->where('slug', $request->get('industry')));
        }

        $items = $query->orderByDesc('published_at')->paginate(min((int) $request->get('per_page', 12), 50));

        return response()->json([
            'data' => $items->items(),
            'meta' => ['current_page' => $items->currentPage(), 'last_page' => $items->lastPage(), 'total' => $items->total()],
        ]);
    }

    public function portfolioShow(string $slug): JsonResponse
    {
        $item = MarketingPortfolioItem::query()->published()
            ->where('slug', $slug)->with(['service', 'industry'])->firstOrFail();

        return response()->json(['data' => $item]);
    }

    public function faq(): JsonResponse
    {
        $items = MarketingFaqItem::query()->published()->orderBy('sort_order')->get();

        return response()->json(['data' => $items]);
    }

    public function downloads(): JsonResponse
    {
        $items = MarketingDownload::query()->published()->with('file')->orderBy('sort_order')->get();

        return response()->json(['data' => $items]);
    }

    public function team(): JsonResponse
    {
        $members = MarketingTeamMember::query()->published()->orderBy('sort_order')->get();

        return response()->json(['data' => $members]);
    }

    public function announcements(): JsonResponse
    {
        $items = MarketingAnnouncement::query()->active()
            ->orderByDesc('pinned')->orderByDesc('id')->get();

        return response()->json(['data' => $items]);
    }

    public function testimonials(): JsonResponse
    {
        $items = MarketingTestimonial::query()->published()->orderBy('sort_order')->get();

        return response()->json(['data' => $items]);
    }

    public function serviceShow(string $slug): JsonResponse
    {
        $service = MarketingService::query()->published()->where('slug', $slug)->with('category')->firstOrFail();

        return response()->json(['data' => $service]);
    }

    public function solutionIndustry(string $industry): JsonResponse
    {
        $row = MarketingSolutionIndustry::query()->where('slug', $industry)
            ->with(['pages' => fn ($q) => $q->published()])
            ->firstOrFail();

        return response()->json(['data' => $row]);
    }

    public function solutionPage(string $industry, string $slug): JsonResponse
    {
        $ind = MarketingSolutionIndustry::query()->where('slug', $industry)->firstOrFail();
        $page = MarketingSolutionPage::query()->published()
            ->where('industry_id', $ind->id)->where('slug', $slug)->firstOrFail();

        return response()->json(['data' => $page]);
    }
}
