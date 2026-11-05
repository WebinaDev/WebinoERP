<?php

namespace Modules\Marketing\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait HandlesMarketingCrud
{
    abstract protected function modelClass(): string;

    protected function validationRules(bool $creating): array
    {
        return [];
    }

    public function index(Request $request): JsonResponse
    {
        $class = $this->modelClass();
        $perPage = min((int) $request->get('per_page', 20), 100);
        $query = $class::query()->orderByDesc('id');

        if ($request->filled('search') && method_exists($class, 'scopeSearch')) {
            $query->search($request->get('search'));
        }

        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $row = $this->modelClass()::query()->findOrFail($id);

        return response()->json(['data' => $row]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->validationRules(true));
        $row = $this->modelClass()::query()->create($data);

        return response()->json(['data' => $row], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $row = $this->modelClass()::query()->findOrFail($id);
        $data = $request->validate($this->validationRules(false));
        // Merge seo JSON so extra keys (live_url, tags, …) are not wiped when only form fields are sent.
        if (array_key_exists('seo', $data) && is_array($data['seo'])) {
            $existing = is_array($row->seo ?? null) ? $row->seo : [];
            $data['seo'] = array_merge($existing, $data['seo']);
        }
        $row->update($data);

        return response()->json(['data' => $row->fresh()]);
    }

    public function destroy(int $id): JsonResponse
    {
        $row = $this->modelClass()::query()->findOrFail($id);
        $row->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }
}
