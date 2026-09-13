<?php

namespace Modules\Marketplace\Http\Controllers;

use App\Http\Controllers\Api\PaginatesApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Modules\Marketplace\Entities\MarketplaceCategory;

class CategoryController extends Controller
{
    use PaginatesApi;

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedResponse(
            MarketplaceCategory::query()->orderBy('sort')->orderBy('name')->paginate($this->perPage($request))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:100|unique:marketplace_categories,slug',
            'sort' => 'nullable|integer',
            'status' => 'nullable|string|max:20',
        ]);
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }
        if ($data['slug'] === '') {
            return response()->json(['message' => 'Unable to derive slug from name'], 422);
        }
        $data['sort'] = $data['sort'] ?? 0;
        $data['status'] = $data['status'] ?? 'active';

        return response()->json(['data' => MarketplaceCategory::create($data), 'message' => 'Created'], 201);
    }

    public function update(Request $request, MarketplaceCategory $category): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'nullable|string|max:100|unique:marketplace_categories,slug,'.$category->id,
            'sort' => 'nullable|integer',
            'status' => 'nullable|string|max:20',
        ]);
        if (array_key_exists('slug', $data) && ($data['slug'] === null || $data['slug'] === '')) {
            $name = $data['name'] ?? $category->name;
            $data['slug'] = Str::slug((string) $name);
        }
        $category->update($data);

        return response()->json(['data' => $category->fresh(), 'message' => 'Updated']);
    }

    public function destroy(MarketplaceCategory $category): Response
    {
        $category->delete();

        return response()->noContent();
    }
}
