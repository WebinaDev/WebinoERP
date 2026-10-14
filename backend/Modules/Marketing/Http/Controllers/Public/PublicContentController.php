<?php

namespace Modules\Marketing\Http\Controllers\Public;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Crm\Entities\CrmLead;
use Modules\Crm\Entities\CrmStatus;
use Modules\Marketing\Entities\MarketingAcademyCourse;
use Modules\Marketing\Entities\MarketingAnnouncement;
use Modules\Marketing\Entities\MarketingBlogCategory;
use Modules\Marketing\Entities\MarketingBlogPost;
use Modules\Marketing\Entities\MarketingDownload;
use Modules\Marketing\Entities\MarketingFaqItem;
use Modules\Marketing\Entities\MarketingMagazinePost;
use Modules\Marketing\Entities\MarketingPage;
use Modules\Marketing\Entities\MarketingPortfolioCategory;
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
        $data = $page->makeHidden(['builder_draft'])->toArray();
        $data['document'] = $page->builder_published;

        return response()->json(['data' => $data]);
    }

    public function blog(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 12), 50);
        $query = MarketingBlogPost::query()->published()->with('category');
        if ($request->filled('category')) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $request->get('category')));
        }
        $posts = $query->orderByDesc('published_at')->paginate($perPage);

        return response()->json([
            'data' => array_map(fn ($p) => self::withReadTime($p->makeHidden('body')->toArray(), (string) $p->body), $posts->items()),
            'meta' => ['current_page' => $posts->currentPage(), 'last_page' => $posts->lastPage(), 'total' => $posts->total()],
        ]);
    }

    /** Blog topics (filter chips). */
    public function blogCategories(): JsonResponse
    {
        return response()->json(['data' => MarketingBlogCategory::query()->orderBy('name')->get(['id', 'slug', 'name'])]);
    }

    /** Minutes at ~200 words per minute (Persian body text), at least 1. */
    private static function withReadTime(array $row, string $body): array
    {
        $words = count(preg_split('/\s+/u', trim(strip_tags($body)), -1, PREG_SPLIT_NO_EMPTY) ?: []);
        $row['read_minutes'] = max(1, (int) ceil($words / 200));

        return $row;
    }

    public function blogShow(string $slug): JsonResponse
    {
        $post = MarketingBlogPost::query()->published()->where('slug', $slug)->with('category')->firstOrFail();
        $related = MarketingBlogPost::query()->published()->where('id', '!=', $post->id)
            ->when($post->category_id, fn ($q) => $q->where('category_id', $post->category_id))
            ->orderByDesc('published_at')->limit(3)->get(['id', 'slug', 'title', 'excerpt', 'cover_url', 'published_at']);
        $data = self::withReadTime($post->toArray(), (string) $post->body);
        $data['related'] = $related;

        return response()->json(['data' => $data]);
    }

    public function magazine(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 12), 50);
        $query = MarketingMagazinePost::query()->published();
        if ($request->filled('issue')) {
            $query->where('issue', $request->get('issue'));
        }
        $posts = $query->orderByDesc('featured')->orderByDesc('published_at')->paginate($perPage);
        $issues = MarketingMagazinePost::query()->published()->whereNotNull('issue')->where('issue', '!=', '')
            ->distinct()->orderBy('issue')->pluck('issue');

        return response()->json([
            'data' => array_map(fn ($p) => $p->makeHidden('body')->toArray(), $posts->items()),
            // The API envelope keeps only data/meta, so the issue list travels in meta.
            'meta' => ['current_page' => $posts->currentPage(), 'last_page' => $posts->lastPage(), 'total' => $posts->total(), 'issues' => $issues],
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
        $query = MarketingPortfolioItem::query()->published()->with(['service', 'industry', 'category']);

        if ($request->filled('service')) {
            $query->whereHas('service', fn ($q) => $q->where('slug', $request->get('service')));
        }
        if ($request->filled('category')) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $request->get('category')));
        }
        if ($request->filled('industry')) {
            $query->whereHas('industry', fn ($q) => $q->where('slug', $request->get('industry')));
        }

        if ($request->get('sort') === 'featured') {
            $query->orderByDesc('featured')->orderBy('sort_order');
        } else {
            $query->orderBy('sort_order');
        }
        $items = $query->orderByDesc('published_at')->orderByDesc('id')->paginate(min((int) $request->get('per_page', 12), 50));

        return response()->json([
            'data' => $items->items(),
            'meta' => ['current_page' => $items->currentPage(), 'last_page' => $items->lastPage(), 'total' => $items->total()],
        ]);
    }

    /** Portfolio categories (filter chips) with their published item counts. */
    public function portfolioCategories(): JsonResponse
    {
        $rows = MarketingPortfolioCategory::query()
            ->withCount(['items' => fn ($q) => $q->where('published', true)])
            ->orderBy('sort_order')->orderBy('id')
            ->get(['id', 'slug', 'name', 'description', 'sort_order']);

        return response()->json(['data' => $rows]);
    }

    public function portfolioShow(string $slug): JsonResponse
    {
        $item = MarketingPortfolioItem::query()->published()
            ->where('slug', $slug)->with(['service', 'industry', 'category'])->firstOrFail();

        return response()->json(['data' => $item]);
    }

    public function faq(): JsonResponse
    {
        $items = MarketingFaqItem::query()->published()->orderBy('sort_order')->orderBy('id')->get(['id', 'group', 'question', 'answer', 'sort_order']);
        $groups = [];
        foreach ($items as $item) {
            $groups[$item->group ?: 'عمومی'][] = $item;
        }

        return response()->json(['data' => $items, 'meta' => ['groups' => array_map(fn ($name, $rows) => ['name' => $name, 'items' => $rows], array_keys($groups), $groups)]]);
    }

    public function downloads(Request $request): JsonResponse
    {
        $query = MarketingDownload::query()->published()->orderBy('sort_order')->orderBy('id');
        if ($request->filled('type')) {
            $query->where('file_type', $request->get('type'));
        }

        // The file link is never listed: gated files are released by downloadRequest(), open ones too (counted).
        return response()->json(['data' => $query->get(['id', 'slug', 'title', 'description', 'category', 'file_type', 'gated', 'related_url', 'sort_order'])]);
    }

    /** Email gate: store a CRM lead (unless ungated and no contact given) and return the file link + follow-up. */
    public function downloadRequest(Request $request, int $id): JsonResponse
    {
        $item = MarketingDownload::query()->published()->with('file')->findOrFail($id);
        if (filled($request->input('_hp')) || ($request->has('_t') && (int) $request->input('_t') < 1500)) {
            return response()->json(['data' => ['url' => null]]);
        }
        $data = $request->validate([
            'name' => [$item->gated ? 'required' : 'nullable', 'string', 'max:120'],
            'email' => array_merge(['nullable', 'email', 'max:190'], $item->gated ? ['required_without:phone'] : []),
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        if (filled($data['name'] ?? null) && (filled($data['email'] ?? null) || filled($data['phone'] ?? null))) {
            $status = CrmStatus::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->first()
                ?? CrmStatus::query()->create(['name' => 'جدید', 'color' => '#0054ff', 'sort_order' => 0, 'is_active' => true]);
            $parts = preg_split('/\s+/u', trim($data['name']), 2) ?: [$data['name']];
            CrmLead::query()->create([
                'topic' => 'دانلود: '.$item->title,
                'first_name' => $parts[0], 'last_name' => $parts[1] ?? '',
                'email' => $data['email'] ?? null, 'mobile' => (string) ($data['phone'] ?? ''), 'phone' => $data['phone'] ?? null,
                'status_id' => $status->id, 'industry' => 'download',
                'description' => "منبع: دانلودها (/downloads)\nفایل: {$item->title}",
            ]);
        }

        $url = $item->file_url ?: ($item->file?->public_url ?: null);

        return response()->json(['data' => ['url' => $url, 'related_url' => $item->related_url ?: '/consultation']]);
    }

    public function team(): JsonResponse
    {
        $members = MarketingTeamMember::query()->published()->orderBy('sort_order')->get();

        return response()->json(['data' => $members]);
    }

    public function announcements(Request $request): JsonResponse
    {
        if (! $request->boolean('archive') && ! $request->filled('type') && ! $request->filled('from')) {
            $items = MarketingAnnouncement::query()->active()->orderByDesc('pinned')->orderByDesc('id')->get();

            return response()->json(['data' => $items]);
        }
        // Archive: every published announcement (also expired ones), optionally by type and date window.
        $query = MarketingAnnouncement::query()->where('published', true);
        if ($request->filled('type')) {
            $query->where('type', $request->get('type'));
        }
        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->where('created_at', '<', $request->date('to'));
        }

        return response()->json(['data' => $query->orderByDesc('pinned')->orderByDesc('created_at')->limit(200)
            ->get(['id', 'slug', 'title', 'summary', 'type', 'pinned', 'starts_at', 'created_at'])]);
    }

    public function announcementShow(string $slug): JsonResponse
    {
        $q = MarketingAnnouncement::query()->where('published', true);
        $item = ctype_digit($slug) ? $q->where('id', (int) $slug)->firstOrFail() : $q->where('slug', $slug)->firstOrFail();

        return response()->json(['data' => $item]);
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
