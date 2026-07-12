<?php

namespace Modules\SiteBuilder\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\SiteBuilder\Entities\WebinoBusinessType;

class BusinessTypeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = WebinoBusinessType::query()->with(['category', 'features'])->orderBy('sort_order');
        if ($request->filled('category_id')) {
            $q->where('category_id', $request->integer('category_id'));
        }
        if ($request->filled('category_slug')) {
            $q->whereHas('category', fn ($c) => $c->where('slug', $request->string('category_slug')));
        }

        return response()->json(['data' => $q->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category_id' => 'required|exists:webino_business_categories,id',
            'slug' => 'required|string|max:64|regex:/^[a-z0-9_-]+$/',
            'name_fa' => 'required|string|max:191',
            'name_en' => 'required|string|max:191',
            'description_fa' => 'nullable|string',
            'description_en' => 'nullable|string',
            'theme_preset' => 'nullable|string|max:64',
            'default_module_slugs' => 'nullable|array',
            'default_module_slugs.*' => 'string|max:64',
            'nav_preset' => 'nullable|array',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'feature_ids' => 'nullable|array',
            'feature_ids.*' => 'integer|exists:webino_dashboard_features,id',
        ]);

        $featureIds = $data['feature_ids'] ?? [];
        unset($data['feature_ids']);

        $exists = WebinoBusinessType::query()
            ->where('category_id', $data['category_id'])
            ->where('slug', $data['slug'])
            ->exists();
        if ($exists) {
            return response()->json(['message' => 'Slug already exists for this category.'], 422);
        }

        $row = WebinoBusinessType::query()->create($data);
        if ($featureIds !== []) {
            $row->features()->sync(collect($featureIds)->mapWithKeys(fn ($id) => [
                $id => ['is_required' => false, 'default_selected' => true],
            ])->all());
        }
        $row->load(['category', 'features']);

        return response()->json(['data' => $row], 201);
    }

    public function show(WebinoBusinessType $type): JsonResponse
    {
        $type->load(['category', 'features', 'packages']);

        return response()->json(['data' => $type]);
    }

    public function update(Request $request, WebinoBusinessType $type): JsonResponse
    {
        $data = $request->validate([
            'category_id' => 'sometimes|exists:webino_business_categories,id',
            'slug' => 'sometimes|string|max:64|regex:/^[a-z0-9_-]+$/',
            'name_fa' => 'sometimes|string|max:191',
            'name_en' => 'sometimes|string|max:191',
            'description_fa' => 'nullable|string',
            'description_en' => 'nullable|string',
            'theme_preset' => 'nullable|string|max:64',
            'default_module_slugs' => 'nullable|array',
            'default_module_slugs.*' => 'string|max:64',
            'nav_preset' => 'nullable|array',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'feature_ids' => 'nullable|array',
            'feature_ids.*' => 'integer|exists:webino_dashboard_features,id',
        ]);

        $featureIds = $data['feature_ids'] ?? null;
        unset($data['feature_ids']);

        if (isset($data['slug']) || isset($data['category_id'])) {
            $catId = $data['category_id'] ?? $type->category_id;
            $slug = $data['slug'] ?? $type->slug;
            $exists = WebinoBusinessType::query()
                ->where('category_id', $catId)
                ->where('slug', $slug)
                ->where('id', '!=', $type->id)
                ->exists();
            if ($exists) {
                return response()->json(['message' => 'Slug already exists for this category.'], 422);
            }
        }

        $type->update($data);
        if (is_array($featureIds)) {
            $type->features()->sync(collect($featureIds)->mapWithKeys(fn ($id) => [
                $id => ['is_required' => false, 'default_selected' => true],
            ])->all());
        }
        $type->load(['category', 'features']);

        return response()->json(['data' => $type]);
    }

    public function destroy(WebinoBusinessType $type): JsonResponse
    {
        $type->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
