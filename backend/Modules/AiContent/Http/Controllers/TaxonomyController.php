<?php

namespace Modules\AiContent\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AiContent\Entities\AiAttrTemplate;
use Modules\AiContent\Entities\AiJob;
use Modules\AiContent\Entities\AiSuggestion;

class TaxonomyController extends Controller
{
    public function suggestCategories(Request $request): JsonResponse
    {
        $data = $request->validate(['kind' => 'required|in:blog,product']);
        $kind = $data['kind'];

        AiSuggestion::query()->updateOrCreate(
            ['kind' => $kind],
            ['suggestions' => [
                'categories' => [
                    ['name' => 'Sample '.$kind.' category A'],
                    ['name' => 'Sample '.$kind.' category B'],
                ],
            ]],
        );

        $job = AiJob::query()->create([
            'job_type' => 'suggest_categories',
            'target_type' => $kind,
            'target_id' => 0,
            'payload' => ['kind' => $kind],
            'status' => 'done',
            'provider' => 'stub',
            'model' => 'placeholder',
            'result_summary' => 'Stub category suggestions',
            'started_at' => now(),
            'finished_at' => now(),
            'attempts' => 1,
        ]);

        return response()->json(['ok' => true, 'job_id' => $job->id]);
    }

    public function getCategorySuggestions(string $kind): JsonResponse
    {
        $row = AiSuggestion::query()->where('kind', $kind)->first();

        return response()->json([
            'kind' => $kind,
            'suggestions' => $row?->suggestions,
        ]);
    }

    public function applyCategorySuggestions(string $kind): JsonResponse
    {
        $row = AiSuggestion::query()->where('kind', $kind)->first();
        $cats = $row?->suggestions['categories'] ?? [];

        return response()->json(['ok' => true, 'count' => count($cats)]);
    }

    public function fillTermsBatch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'taxonomy' => 'required|in:product_cat,product_brand,category',
            'ids' => 'nullable|array',
        ]);

        $job = AiJob::query()->create([
            'job_type' => 'fill_terms',
            'target_type' => $data['taxonomy'],
            'target_id' => 0,
            'payload' => $data,
            'status' => 'done',
            'provider' => 'stub',
            'model' => 'placeholder',
            'result_summary' => 'Stub term fill',
            'started_at' => now(),
            'finished_at' => now(),
            'attempts' => 1,
        ]);

        return response()->json(['ok' => true, 'count' => 1, 'job_id' => $job->id]);
    }

    public function attrTemplates(): JsonResponse
    {
        $items = AiAttrTemplate::query()->orderBy('id')->get()->map(fn (AiAttrTemplate $t) => [
            'id' => $t->id,
            'product_cat_id' => $t->product_cat_id,
            'category_name' => $t->category_name ?? '',
            'attribute_ids' => $t->attribute_ids ?? [],
            'labels' => $t->labels ?? [],
        ])->values();

        return response()->json(['items' => $items]);
    }

    public function saveAttrTemplate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_cat_id' => 'required|integer',
            'category_name' => 'nullable|string|max:255',
            'attribute_ids' => 'nullable|array',
            'draft' => 'nullable',
            'labels' => 'nullable|array',
        ]);

        $labels = $data['labels'] ?? null;
        if ($labels === null && isset($data['draft']['attributes']) && is_array($data['draft']['attributes'])) {
            $labels = $data['draft']['attributes'];
        }

        $row = AiAttrTemplate::query()->updateOrCreate(
            ['product_cat_id' => $data['product_cat_id']],
            [
                'category_name' => $data['category_name'] ?? null,
                'attribute_ids' => $data['attribute_ids'] ?? [],
                'labels' => $labels,
                'draft' => $data['draft'] ?? null,
            ],
        );

        return response()->json(['ok' => true, 'data' => $row]);
    }

    public function suggestAttrTemplate(int $catId): JsonResponse
    {
        $job = AiJob::query()->create([
            'job_type' => 'attr_suggest',
            'target_type' => 'product_cat',
            'target_id' => $catId,
            'payload' => ['product_cat_id' => $catId],
            'status' => 'done',
            'provider' => 'stub',
            'model' => 'placeholder',
            'result_summary' => 'Stub attribute draft',
            'started_at' => now(),
            'finished_at' => now(),
            'attempts' => 1,
        ]);

        AiAttrTemplate::query()->updateOrCreate(
            ['product_cat_id' => $catId],
            [
                'category_name' => 'Category #'.$catId,
                'draft' => [
                    'attributes' => [
                        ['label' => 'Color', 'slug' => 'color', 'options' => ['Red', 'Blue']],
                        ['label' => 'Size', 'slug' => 'size', 'options' => ['S', 'M', 'L']],
                    ],
                ],
                'labels' => [],
                'attribute_ids' => [],
            ],
        );

        return response()->json(['ok' => true, 'job_id' => $job->id]);
    }

    public function attrDraft(int $catId): JsonResponse
    {
        $row = AiAttrTemplate::query()->where('product_cat_id', $catId)->first();

        return response()->json([
            'product_cat_id' => $catId,
            'draft' => $row?->draft,
            'template' => $row ? [
                'attribute_ids' => $row->attribute_ids ?? [],
                'labels' => $row->labels ?? [],
            ] : null,
            'discovered' => $row?->labels ?? [],
        ]);
    }

    public function deleteAttrTemplate(int $catId): JsonResponse
    {
        AiAttrTemplate::query()->where('product_cat_id', $catId)->delete();

        return response()->json(['ok' => true]);
    }
}
