<?php

namespace Modules\SiteBuilder\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\SiteBuilder\Entities\WebinoBusinessCategory;

class BusinessCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = WebinoBusinessCategory::query()
            ->with(['types' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'slug' => 'required|string|max:64|regex:/^[a-z0-9_-]+$/|unique:webino_business_categories,slug',
            'name_fa' => 'required|string|max:191',
            'name_en' => 'required|string|max:191',
            'icon' => 'nullable|string|max:64',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $row = WebinoBusinessCategory::query()->create($data);

        return response()->json(['data' => $row], 201);
    }

    public function show(WebinoBusinessCategory $category): JsonResponse
    {
        $category->load('types.features');

        return response()->json(['data' => $category]);
    }

    public function update(Request $request, WebinoBusinessCategory $category): JsonResponse
    {
        $data = $request->validate([
            'slug' => 'sometimes|string|max:64|regex:/^[a-z0-9_-]+$/|unique:webino_business_categories,slug,'.$category->id,
            'name_fa' => 'sometimes|string|max:191',
            'name_en' => 'sometimes|string|max:191',
            'icon' => 'nullable|string|max:64',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $category->update($data);

        return response()->json(['data' => $category->fresh()]);
    }

    public function destroy(WebinoBusinessCategory $category): JsonResponse
    {
        $category->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
