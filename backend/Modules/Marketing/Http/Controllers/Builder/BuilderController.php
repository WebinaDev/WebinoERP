<?php

namespace Modules\Marketing\Http\Controllers\Builder;

use Modules\Marketing\Http\Controllers\Controller;
use Modules\Marketing\Entities\MarketingBlogPost as BlogPost;
use Modules\Marketing\Entities\BuilderTemplate;
use Modules\Marketing\Entities\MarketingPage;
use Modules\Marketing\Entities\MarketingMagazinePost as MagazineArticle;
use Modules\Sales\Entities\SalesCatalogItem as Product;
use Modules\Marketing\Entities\MarketingSiteSetting;
use Modules\Marketing\Services\Builder\BuilderSite;
use Modules\Marketing\Services\Builder\BuilderDocumentRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BuilderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tid = BuilderSite::ID;
        $settings = MarketingSiteSetting::current();
        $pages = MarketingPage::query()->where('tenant_id', $tid)->where(function ($q) {
            $q->whereNull('status')->orWhere('status', '!=', 'trash');
        })->orderBy('title_fa')->get();
        $templates = BuilderTemplate::query()
            ->where('tenant_id', $tid)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get()
            ->unique('kind')
            ->keyBy('kind');

        return response()->json([
            'data' => [
                'active_theme_slug' => $settings->active_theme_slug,
                'pages' => $pages->map(fn (MarketingPage $page) => $this->serializeList($page))->values(),
                'templates' => [
                    'header' => $this->templateSummary($templates->get('header')),
                    'footer' => $this->templateSummary($templates->get('footer')),
                ],
            ],
        ]);
    }

    public function show(Request $request, int $page): JsonResponse
    {
        $row = $this->page($request, $page);

        return response()->json(['data' => $this->serializeDetail($row)]);
    }

    public function store(Request $request): JsonResponse
    {
        \Modules\Marketing\Services\Builder\BuilderAccess::authorize($request, 'edit');
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:120',
            'document' => 'nullable|array',
            'seo' => 'nullable|array',
            'seo.title' => 'nullable|string|max:120',
            'seo.description' => 'nullable|string|max:320',
            'seo.og_image' => 'nullable|string|max:500',
            'seo.canonical' => 'nullable|string|max:500',
            'seo.noindex' => 'nullable|boolean',
        ]);
        $tid = BuilderSite::ID;
        $slug = $this->slug($data['slug'] ?? null, $data['title']);
        abort_if(
            MarketingPage::query()->where('tenant_id', $tid)->where('slug', $slug)->exists(),
            422,
            'slug taken'
        );

        $row = MarketingPage::query()->create([
            'tenant_id' => $tid,
            'title' => $data['title'],
            'slug' => $slug,
            'body' => null,
            'published' => false,
            'status' => 'draft',
            'builder_draft' => $this->document($data['document'] ?? ['version' => 1, 'sections' => []]),
            'meta' => isset($data['seo']) ? self::withSeo([], $data['seo']) : null,
        ]);

        return response()->json(['data' => $this->serializeDetail($row)], 201);
    }

    public function update(Request $request, int $page): JsonResponse
    {
        \Modules\Marketing\Services\Builder\BuilderAccess::authorize($request, 'edit');
        $row = $this->page($request, $page);
        $other = app(\Modules\Marketing\Services\Builder\BuilderPageLifecycle::class)->lockedByOther($row, $request->user());
        abort_if($other !== null, 423, 'locked by '.($other['name'] ?? ''));
        $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:120',
            'document' => 'sometimes|array',
            'seo' => 'sometimes|array',
            'seo.title' => 'nullable|string|max:120',
            'seo.description' => 'nullable|string|max:320',
            'seo.og_image' => 'nullable|string|max:500',
            'seo.canonical' => 'nullable|string|max:500',
            'seo.noindex' => 'nullable|boolean',
        ]);
        if (array_key_exists('seo', $data)) {
            $row->meta = self::withSeo($row->meta, $data['seo']);
        }
        if (isset($data['slug'])) {
            $slug = $this->slug($data['slug'], $row->title);
            abort_if(
                MarketingPage::query()->where('tenant_id', $row->tenant_id)->where('slug', $slug)->where('id', '!=', $row->id)->exists(),
                422,
                'slug taken'
            );
            app(\Modules\Marketing\Services\Builder\BuilderPageLifecycle::class)->recordSlugChange($row, (string) $row->slug, $slug);
            $row->slug = $slug;
        }
        if (isset($data['title'])) {
            $row->title = $data['title'];
        }
        if (array_key_exists('document', $data)) {
            $row->builder_draft = $this->document($data['document']);
        }
        $row->save();

        return response()->json(['data' => $this->serializeDetail($row)]);
    }

    public function publish(Request $request, int $page): JsonResponse
    {
        \Modules\Marketing\Services\Builder\BuilderAccess::authorize($request, 'publish');
        $row = $this->page($request, $page);
        \Modules\Marketing\Support\StatusTrash::guardPublish($row);
        abort_if(! is_array($row->builder_draft), 422, 'draft missing');
        $row->builder_published = $row->builder_draft;
        $row->published = true;
        $row->status = 'published';
        $row->save();

        return response()->json(['data' => $this->serializeDetail($row)]);
    }

    public function showProduct(Request $request, Product $product): JsonResponse
    {
        
        return response()->json(['data' => $this->metaDocument($product, (string) $product->name, (string) $product->slug, (string) $product->status)]);
    }

    public function updateProduct(Request $request, Product $product): JsonResponse
    {
        \Modules\Marketing\Services\Builder\BuilderAccess::authorize($request, 'edit');
                $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'document' => 'sometimes|array',
        ]);
        if (isset($data['title'])) {
            $product->name = $data['title'];
        }
        if (array_key_exists('document', $data)) {
            $meta = is_array($product->meta) ? $product->meta : [];
            $meta['builder_document'] = $this->document($data['document']);
            $product->meta = $meta;
        }
        $product->save();

        return response()->json(['data' => $this->metaDocument($product, (string) $product->name, (string) $product->slug, (string) $product->status)]);
    }

    public function publishProduct(Request $request, Product $product): JsonResponse
    {
        \Modules\Marketing\Services\Builder\BuilderAccess::authorize($request, 'publish');
                \Modules\Marketing\Support\StatusTrash::guardPublish($product);
        $meta = is_array($product->meta) ? $product->meta : [];
        abort_if(! is_array($meta['builder_document'] ?? null), 422, 'draft missing');
        $meta['builder_published'] = $meta['builder_document'];
        $product->meta = $meta;
        $product->save();

        return response()->json(['data' => $this->metaDocument($product, (string) $product->name, (string) $product->slug, (string) $product->status)]);
    }

    public function showPost(Request $request, int $post): JsonResponse
    {
        $row = BlogPost::query()->findOrFail($post);

        return response()->json(['data' => $this->columnDocument($row, 'builder_draft', 'builder_published')]);
    }

    public function updatePost(Request $request, int $post): JsonResponse
    {
        \Modules\Marketing\Services\Builder\BuilderAccess::authorize($request, 'edit');
        $row = BlogPost::query()->findOrFail($post);
        $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'document' => 'sometimes|array',
        ]);
        if (isset($data['title'])) {
            $row->title = $data['title'];
        }
        if (array_key_exists('document', $data)) {
            $row->builder_draft = $this->document($data['document']);
        }
        $row->save();

        return response()->json(['data' => $this->columnDocument($row, 'builder_draft', 'builder_published')]);
    }

    public function publishPost(Request $request, int $post): JsonResponse
    {
        \Modules\Marketing\Services\Builder\BuilderAccess::authorize($request, 'publish');
        $row = BlogPost::query()->findOrFail($post);
        \Modules\Marketing\Support\StatusTrash::guardPublish($row);
        abort_if(! is_array($row->builder_draft), 422, 'draft missing');
        $row->builder_published = $row->builder_draft;
        $row->status = 'published';
        if (! $row->published_at) {
            $row->published_at = now();
        }
        $row->save();

        return response()->json(['data' => $this->columnDocument($row, 'builder_draft', 'builder_published')]);
    }

    public function showArticle(Request $request, int $article): JsonResponse
    {
        $row = MagazineArticle::query()->findOrFail($article);

        return response()->json(['data' => $this->metaDocument($row, (string) $row->title, (string) $row->slug, (string) $row->status)]);
    }

    public function updateArticle(Request $request, int $article): JsonResponse
    {
        \Modules\Marketing\Services\Builder\BuilderAccess::authorize($request, 'edit');
        $row = MagazineArticle::query()->findOrFail($article);
        $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'document' => 'sometimes|array',
        ]);
        if (isset($data['title'])) {
            $row->title = $data['title'];
        }
        if (array_key_exists('document', $data)) {
            $meta = is_array($row->meta) ? $row->meta : [];
            $meta['builder_document'] = $this->document($data['document']);
            $row->meta = $meta;
        }
        $row->save();

        return response()->json(['data' => $this->metaDocument($row, (string) $row->title, (string) $row->slug, (string) $row->status)]);
    }

    public function publishArticle(Request $request, int $article): JsonResponse
    {
        \Modules\Marketing\Services\Builder\BuilderAccess::authorize($request, 'publish');
        $row = MagazineArticle::query()->findOrFail($article);
        \Modules\Marketing\Support\StatusTrash::guardPublish($row);
        $meta = is_array($row->meta) ? $row->meta : [];
        abort_if(! is_array($meta['builder_document'] ?? null), 422, 'draft missing');
        $meta['builder_published'] = $meta['builder_document'];
        $row->meta = $meta;
        $row->status = 'published';
        if (! $row->published_at) {
            $row->published_at = now();
        }
        $row->save();

        return response()->json(['data' => $this->metaDocument($row, (string) $row->title, (string) $row->slug, (string) $row->status)]);
    }

    public function destroy(Request $request, int $page): JsonResponse
    {
        \Modules\Marketing\Services\Builder\BuilderAccess::authorize($request, 'edit');
        $row = $this->page($request, $page);
        $result = \Modules\Marketing\Support\StatusTrash::trashOrDelete($row, $request->boolean('force'), 'status', 'draft', ['published', 'draft', 'private', 'pending']);
        if (! empty($result['trashed'])) {
            $row->refresh();
            if ($row->status === 'trash') {
                $row->published = false;
                $row->save();
            }
        }

        return response()->json(['data' => $result]);
    }

    public function showTemplate(Request $request, string $kind): JsonResponse
    {
        $kind = $this->kind($kind);
        $row = BuilderTemplate::preferred(BuilderSite::ID, $kind);

        return response()->json(['data' => [
            'kind' => $kind,
            'title' => $row?->title,
            'document' => $row?->draft,
            'published_document' => $row?->published,
        ]]);
    }

    public function saveTemplate(Request $request, string $kind): JsonResponse
    {
        \Modules\Marketing\Services\Builder\BuilderAccess::authorize($request, 'design');
        $kind = $this->kind($kind);
        $data = $request->validate([
            'title' => 'nullable|string|max:255',
            'document' => 'required|array',
        ]);
        $tid = BuilderSite::ID;
        $row = BuilderTemplate::preferred($tid, $kind) ?? new BuilderTemplate([
            'tenant_id' => $tid,
            'kind' => $kind,
            'slug' => $kind,
            'is_default' => true,
            'priority' => 0,
        ]);
        $row->title = $data['title'] ?? $row->title;
        $row->draft = $this->document($data['document']);
        if (! $row->slug) {
            $row->slug = $kind;
        }
        $row->save();
        if (! $row->is_default) {
            $this->promoteDefault($row);
        }

        return response()->json(['data' => [
            'kind' => $row->kind,
            'title' => $row->title,
            'document' => $row->draft,
            'published_document' => $row->published,
        ]]);
    }

    public function publishTemplate(Request $request, string $kind): JsonResponse
    {
        \Modules\Marketing\Services\Builder\BuilderAccess::authorize($request, 'design');
        $kind = $this->kind($kind);
        $row = BuilderTemplate::preferred(BuilderSite::ID, $kind);
        abort_unless($row, 404);
        abort_if(! is_array($row->draft), 422, 'draft missing');
        $row->published = $row->draft;
        $row->save();

        return response()->json(['data' => [
            'kind' => $row->kind,
            'title' => $row->title,
            'document' => $row->draft,
            'published_document' => $row->published,
        ]]);
    }

    private function page(Request $request, int $page): MarketingPage
    {
        return MarketingPage::query()->where('tenant_id', BuilderSite::ID)->findOrFail($page);
    }

    private function kind(string $kind): string
    {
        abort_unless(in_array($kind, ['header', 'footer'], true), 404);

        return $kind;
    }

    private function slug(?string $slug, string $title): string
    {
        $raw = trim((string) $slug);
        if ($raw === '') {
            $raw = Str::slug($title);
        }
        $raw = Str::slug($raw, '-');

        return $raw !== '' ? $raw : 'page-'.Str::lower(Str::random(4));
    }

    /** @param  array<string, mixed>|null  $document */
    private function document(?array $document): array
    {
        return BuilderDocumentRules::normalize($document);
    }

    private function promoteDefault(BuilderTemplate $row): void
    {
        DB::transaction(function () use ($row) {
            BuilderTemplate::query()
                ->where('tenant_id', $row->tenant_id)
                ->where('kind', $row->kind)
                ->where('id', '!=', $row->id)
                ->update(['is_default' => false]);
            $row->is_default = true;
            $row->save();
        });
    }

    /** @return array<string, mixed> */
    private function metaDocument(object $row, string $title, string $slug, string $status): array
    {
        $meta = is_array($row->meta ?? null) ? $row->meta : [];

        return [
            'id' => $row->id,
            'title' => $title,
            'slug' => $slug,
            'status' => $status,
            'document' => $meta['builder_document'] ?? ['version' => 1, 'sections' => []],
            'published_document' => $meta['builder_published'] ?? null,
        ];
    }

    /** @return array<string, mixed> */
    private function columnDocument(object $row, string $draftKey, string $publishedKey): array
    {
        return [
            'id' => $row->id,
            'title' => (string) ($row->title ?? ''),
            'slug' => (string) ($row->slug ?? ''),
            'status' => (string) ($row->status ?? 'draft'),
            'document' => $row->{$draftKey} ?? ['version' => 1, 'sections' => []],
            'published_document' => $row->{$publishedKey} ?? null,
        ];
    }

    /** @return array<string, mixed> */
    private function serializeList(MarketingPage $page): array
    {
        return [
            'id' => $page->id,
            'title' => $page->title,
            'slug' => $page->slug,
            'status' => $page->status ?: ($page->published ? 'published' : 'draft'),
            'has_draft' => is_array($page->builder_draft),
            'has_published' => is_array($page->builder_published),
        ];
    }

    /** @return array<string, mixed> */
    /** Page SEO lives in marketing_pages.meta.seo; other meta keys are kept. */
    public static function withSeo(mixed $meta, array $seo): array
    {
        $meta = is_array($meta) ? $meta : [];
        $clean = array_filter([
            'title' => trim((string) ($seo['title'] ?? '')),
            'description' => trim((string) ($seo['description'] ?? '')),
            'og_image' => trim((string) ($seo['og_image'] ?? '')),
            'canonical' => trim((string) ($seo['canonical'] ?? '')),
            'noindex' => ! empty($seo['noindex']) ? true : null,
        ], fn ($v) => $v !== '' && $v !== null);
        $meta['seo'] = $clean;

        return $meta;
    }

    private function serializeDetail(MarketingPage $page): array
    {
        return [
            ...$this->serializeList($page),
            'seo' => (object) (is_array($page->meta) ? ($page->meta['seo'] ?? []) : []),
            'document' => $page->builder_draft ?? ['version' => 1, 'sections' => []],
            'published_document' => $page->builder_published,
        ];
    }

    /** @return array<string, mixed> */
    private function templateSummary(?BuilderTemplate $row): array
    {
        return [
            'title' => $row?->title,
            'has_draft' => is_array($row?->draft),
            'has_published' => is_array($row?->published),
        ];
    }
}
