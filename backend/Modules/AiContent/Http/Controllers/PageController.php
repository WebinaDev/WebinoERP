<?php

namespace Modules\AiContent\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AiContent\Entities\AiPage;

class PageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AiPage::query()->orderByDesc('id');
        if ($search = $request->string('search')->toString()) {
            $query->where('title', 'like', '%'.$search.'%');
        }
        $page = max(1, $request->integer('page', 1));
        $perPage = min(100, max(1, $request->integer('per_page', 50)));
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $items = collect($paginator->items())->map(fn (AiPage $row) => [
            'id' => $row->id,
            'title' => $row->title,
            'status' => $row->status,
            'modified' => optional($row->updated_at)?->toIso8601String() ?? '',
            'url' => (string) ($row->url ?? ''),
            'page_prompt' => (string) ($row->page_prompt ?? ''),
            'has_elementor' => (bool) $row->has_elementor,
            'elementor_url' => (string) ($row->elementor_url ?? ''),
        ])->values();

        return response()->json([
            'items' => $items,
            'page' => $paginator->currentPage(),
            'found' => $paginator->total(),
            'elementor' => true,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'status' => 'nullable|string|max:20',
            'url' => 'nullable|string|max:500',
            'page_prompt' => 'nullable|string',
            'has_elementor' => 'nullable|boolean',
            'elementor_url' => 'nullable|string|max:500',
            'content' => 'nullable|string',
        ]);

        $page = AiPage::query()->create([
            'title' => $data['title'],
            'status' => $data['status'] ?? 'draft',
            'url' => $data['url'] ?? null,
            'page_prompt' => $data['page_prompt'] ?? null,
            'has_elementor' => $data['has_elementor'] ?? false,
            'elementor_url' => $data['elementor_url'] ?? null,
            'content' => $data['content'] ?? null,
        ]);

        return response()->json(['data' => $page], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $page = AiPage::query()->findOrFail($id);
        $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'status' => 'nullable|string|max:20',
            'url' => 'nullable|string|max:500',
            'page_prompt' => 'nullable|string',
            'has_elementor' => 'nullable|boolean',
            'elementor_url' => 'nullable|string|max:500',
            'content' => 'nullable|string',
        ]);
        $page->update($data);

        return response()->json(['data' => $page->fresh()]);
    }

    public function destroy(int $id): JsonResponse
    {
        AiPage::query()->whereKey($id)->delete();

        return response()->json(['ok' => true]);
    }
}
