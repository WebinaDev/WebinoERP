<?php

namespace Modules\AiContent\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AiContent\Entities\AiJob;
use Modules\AiContent\Entities\AiProduct;

class ProductController extends Controller
{
    public function incomplete(Request $request): JsonResponse
    {
        $limit = min(200, max(1, $request->integer('limit', 50)));
        $items = AiProduct::query()
            ->where('status', 'incomplete')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (AiProduct $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'missing' => $p->missing ?? ['description'],
            ])
            ->values();

        return response()->json([
            'items' => $items,
            'total' => AiProduct::query()->where('status', 'incomplete')->count(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $query = AiProduct::query()->orderByDesc('id');
        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }
        $items = $query->limit(200)->get();

        return response()->json(['items' => $items, 'total' => $items->count()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100',
            'missing' => 'nullable|array',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string',
            'status' => 'nullable|string|max:20',
            'meta' => 'nullable|array',
        ]);

        $product = AiProduct::query()->create([
            'name' => $data['name'],
            'sku' => $data['sku'] ?? null,
            'missing' => $data['missing'] ?? ['description', 'short_description'],
            'description' => $data['description'] ?? null,
            'short_description' => $data['short_description'] ?? null,
            'status' => $data['status'] ?? 'incomplete',
            'meta' => $data['meta'] ?? null,
        ]);

        return response()->json(['data' => $product], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $product = AiProduct::query()->findOrFail($id);
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'sku' => 'nullable|string|max:100',
            'missing' => 'nullable|array',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string',
            'status' => 'nullable|string|max:20',
            'meta' => 'nullable|array',
        ]);
        $product->update($data);

        return response()->json(['data' => $product->fresh()]);
    }

    public function destroy(int $id): JsonResponse
    {
        AiProduct::query()->whereKey($id)->delete();

        return response()->json(['ok' => true]);
    }

    public function fillBatch(Request $request): JsonResponse
    {
        $ids = $request->input('ids');
        $query = AiProduct::query()->where('status', 'incomplete');
        if (is_array($ids) && $ids !== []) {
            $query->whereIn('id', $ids);
        }
        $products = $query->limit(100)->get();
        $jobIds = [];
        foreach ($products as $product) {
            $job = AiJob::query()->create([
                'job_type' => 'product',
                'target_type' => 'product',
                'target_id' => $product->id,
                'payload' => ['title' => $product->name, 'id' => $product->id],
                'status' => 'pending',
                'provider' => 'stub',
                'model' => 'placeholder',
                'result_summary' => null,
            ]);
            $jobIds[] = $job->id;
            $product->update([
                'description' => $product->description ?: '[AI stub] Product description for '.$product->name,
                'short_description' => $product->short_description ?: '[AI stub] Short description',
                'missing' => [],
                'status' => 'complete',
            ]);
            $job->update([
                'status' => 'done',
                'result_summary' => 'Placeholder product content stored.',
                'finished_at' => now(),
                'started_at' => now(),
                'attempts' => 1,
            ]);
        }

        return response()->json(['ok' => true, 'job_ids' => $jobIds, 'count' => count($jobIds)]);
    }
}
