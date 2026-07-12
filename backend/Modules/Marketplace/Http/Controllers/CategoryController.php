<?php

namespace Modules\Marketplace\Http\Controllers;

use App\Http\Controllers\Api\PaginatesApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Marketplace\Entities\MarketplaceCategory;

class CategoryController extends Controller
{
    use PaginatesApi;

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedResponse(MarketplaceCategory::query()->orderBy('name')->paginate($this->perPage($request)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:100|unique:marketplace_categories,slug',
        ]);

        return response()->json(['data' => MarketplaceCategory::create($data), 'message' => 'Created'], 201);
    }

    public function update(Request $request, MarketplaceCategory $category): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:100|unique:marketplace_categories,slug,'.$category->id,
        ]);
        $category->update($data);

        return response()->json(['data' => $category->fresh(), 'message' => 'Updated']);
    }

    public function destroy(MarketplaceCategory $category): JsonResponse
    {
        $category->delete();

        return response()->noContent();
    }
}
