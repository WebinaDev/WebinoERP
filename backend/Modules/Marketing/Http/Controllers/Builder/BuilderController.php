<?php

namespace Modules\Marketing\Http\Controllers\Builder;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Marketing\Entities\BuilderTemplate;
use Modules\Marketing\Entities\MarketingPage;
use Modules\Marketing\Entities\MarketingSiteSetting;
use Modules\Marketing\Http\Controllers\Controller;
use Modules\Marketing\Services\Builder\BuilderDocumentRules;
use Modules\Marketing\Services\Builder\BuilderSite;

class BuilderController extends Controller
{
    public function index(): JsonResponse
    {
        $settings = MarketingSiteSetting::current();
        $pages = MarketingPage::query()->orderBy('title_fa')->get();
        $templates = BuilderTemplate::query()
            ->where('tenant_id', BuilderSite::ID)
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

    public function show(int $page): JsonResponse
    {
        return response()->json(['data' => $this->serializeDetail($this->page($page))]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:120',
            'document' => 'nullable|array',
        ]);
        $slug = $this->slug($data['slug'] ?? null, $data['title']);
        abort_if(MarketingPage::query()->where('slug', $slug)->exists(), 422, 'slug taken');

        $row = MarketingPage::query()->create([
            'title_fa' => $data['title'],
            'slug' => $slug,
            'body_fa' => null,
            'published' => false,
            'status' => 'draft',
            'builder_draft' => $this->document($data['document'] ?? ['version' => 1, 'sections' => []]),
        ]);

        return response()->json(['data' => $this->serializeDetail($row)], 201);
    }

    public function update(Request $request, int $page): JsonResponse
    {
        $row = $this->page($page);
        $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:120',
            'document' => 'sometimes|array',
        ]);
        if (isset($data['slug'])) {
            $slug = $this->slug($data['slug'], $row->title_fa);
            abort_if(
                MarketingPage::query()->where('slug', $slug)->where('id', '!=', $row->id)->exists(),
                422,
                'slug taken'
            );
            $row->slug = $slug;
        }
        if (isset($data['title'])) {
            $row->title_fa = $data['title'];
        }
        if (array_key_exists('document', $data)) {
            $row->builder_draft = $this->document($data['document']);
        }
        $row->save();

        return response()->json(['data' => $this->serializeDetail($row)]);
    }

    public function publish(int $page): JsonResponse
    {
        $row = $this->page($page);
        abort_if(! is_array($row->builder_draft), 422, 'draft missing');
        $row->builder_published = $row->builder_draft;
        $row->published = true;
        $row->status = 'published';
        $row->save();

        return response()->json(['data' => $this->serializeDetail($row)]);
    }

    public function destroy(int $page): JsonResponse
    {
        $this->page($page)->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function showTemplate(string $kind): JsonResponse
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
        $kind = $this->kind($kind);
        $data = $request->validate([
            'title' => 'nullable|string|max:255',
            'document' => 'required|array',
        ]);
        $row = BuilderTemplate::preferred(BuilderSite::ID, $kind) ?? new BuilderTemplate([
            'tenant_id' => BuilderSite::ID,
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

    public function publishTemplate(string $kind): JsonResponse
    {
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

    private function page(int $page): MarketingPage
    {
        return MarketingPage::query()->findOrFail($page);
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
    private function serializeList(MarketingPage $page): array
    {
        $status = $page->status ?: ($page->published ? 'published' : 'draft');

        return [
            'id' => $page->id,
            'title' => $page->title_fa,
            'slug' => $page->slug,
            'status' => $status,
            'has_draft' => is_array($page->builder_draft),
            'has_published' => is_array($page->builder_published),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeDetail(MarketingPage $page): array
    {
        return [
            ...$this->serializeList($page),
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
