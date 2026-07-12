<?php

namespace Modules\SiteBuilder\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\SiteBuilder\Entities\WebinoDashboardFeature;

class DashboardFeatureController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = WebinoDashboardFeature::query()->orderBy('sort_order')->get();

        return response()->json(['data' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'slug' => 'required|string|max:64|regex:/^[a-z0-9_]+$/|unique:webino_dashboard_features,slug',
            'name_fa' => 'required|string|max:191',
            'name_en' => 'required|string|max:191',
            'module_slug' => 'nullable|string|max:64',
            'is_addon' => 'nullable|boolean',
            'default_enabled' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $row = WebinoDashboardFeature::query()->create($data);

        return response()->json(['data' => $row], 201);
    }

    public function show(WebinoDashboardFeature $feature): JsonResponse
    {
        return response()->json(['data' => $feature]);
    }

    public function update(Request $request, WebinoDashboardFeature $feature): JsonResponse
    {
        $data = $request->validate([
            'slug' => 'sometimes|string|max:64|regex:/^[a-z0-9_]+$/|unique:webino_dashboard_features,slug,'.$feature->id,
            'name_fa' => 'sometimes|string|max:191',
            'name_en' => 'sometimes|string|max:191',
            'module_slug' => 'nullable|string|max:64',
            'is_addon' => 'nullable|boolean',
            'default_enabled' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $feature->update($data);

        return response()->json(['data' => $feature->fresh()]);
    }

    public function destroy(WebinoDashboardFeature $feature): JsonResponse
    {
        $feature->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
