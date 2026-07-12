<?php

namespace Modules\SiteBuilder\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\SiteBuilder\Entities\WebinoPackage;

class PackageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = WebinoPackage::query()->with(['businessType.category', 'features'])->orderBy('sku');
        if ($request->filled('business_type_id')) {
            $q->where('business_type_id', $request->integer('business_type_id'));
        }

        return response()->json(['data' => $q->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sku' => 'required|string|max:128|regex:/^[a-zA-Z0-9._-]+$/|unique:webino_packages,sku',
            'name_fa' => 'required|string|max:191',
            'name_en' => 'required|string|max:191',
            'business_type_id' => 'required|exists:webino_business_types,id',
            'price' => 'nullable|integer|min:0',
            'billing_period' => 'nullable|string|max:32',
            'is_active' => 'nullable|boolean',
            'feature_ids' => 'nullable|array',
            'feature_ids.*' => 'integer|exists:webino_dashboard_features,id',
        ]);

        $featureIds = $data['feature_ids'] ?? [];
        unset($data['feature_ids']);

        $row = WebinoPackage::query()->create($data);
        if ($featureIds !== []) {
            $row->features()->sync($featureIds);
        }
        $row->load(['businessType.category', 'features']);

        return response()->json(['data' => $row], 201);
    }

    public function show(WebinoPackage $package): JsonResponse
    {
        $package->load(['businessType.category', 'features']);

        return response()->json(['data' => $package]);
    }

    public function update(Request $request, WebinoPackage $package): JsonResponse
    {
        $data = $request->validate([
            'sku' => 'sometimes|string|max:128|regex:/^[a-zA-Z0-9._-]+$/|unique:webino_packages,sku,'.$package->id,
            'name_fa' => 'sometimes|string|max:191',
            'name_en' => 'sometimes|string|max:191',
            'business_type_id' => 'sometimes|exists:webino_business_types,id',
            'price' => 'nullable|integer|min:0',
            'billing_period' => 'nullable|string|max:32',
            'is_active' => 'nullable|boolean',
            'feature_ids' => 'nullable|array',
            'feature_ids.*' => 'integer|exists:webino_dashboard_features,id',
        ]);

        $featureIds = $data['feature_ids'] ?? null;
        unset($data['feature_ids']);

        $package->update($data);
        if (is_array($featureIds)) {
            $package->features()->sync($featureIds);
        }
        $package->load(['businessType.category', 'features']);

        return response()->json(['data' => $package]);
    }

    public function destroy(WebinoPackage $package): JsonResponse
    {
        $package->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
