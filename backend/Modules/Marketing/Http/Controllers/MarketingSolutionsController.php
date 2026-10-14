<?php

namespace Modules\Marketing\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Marketing\Entities\MarketingSolutionIndustry;
use Modules\Marketing\Entities\MarketingSolutionPage;

class MarketingSolutionsController extends Controller
{
    public function industries(): JsonResponse
    {
        $rows = MarketingSolutionIndustry::query()
            ->orderBy('sort_order')->with('pages')->get();

        return response()->json(['data' => $rows]);
    }

    public function storeIndustry(Request $request): JsonResponse
    {
        $data = $request->validate([
            'slug' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
        ]);
        $row = MarketingSolutionIndustry::query()->create($data);

        return response()->json(['data' => $row], 201);
    }

    public function updateIndustry(Request $request, int $id): JsonResponse
    {
        $row = MarketingSolutionIndustry::query()->findOrFail($id);
        $data = $request->validate([
            'slug' => 'sometimes|string|max:255',
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
        ]);
        $row->update($data);

        return response()->json(['data' => $row->fresh()]);
    }

    public function destroyIndustry(int $id): JsonResponse
    {
        MarketingSolutionIndustry::query()->findOrFail($id)->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function pages(int $industryId): JsonResponse
    {
        $pages = MarketingSolutionPage::query()
            ->where('industry_id', $industryId)->orderBy('id')->get();

        return response()->json(['data' => $pages]);
    }

    public function storePage(Request $request, int $industryId): JsonResponse
    {
        $data = $request->validate([
            'slug' => 'required|string|max:255',
            'title' => 'required|string|max:255',
            'body' => 'nullable|string',
            'published' => 'nullable|boolean',
        ]);
        $row = MarketingSolutionPage::query()->create($data + ['industry_id' => $industryId]);

        return response()->json(['data' => $row], 201);
    }

    public function updatePage(Request $request, int $id): JsonResponse
    {
        $row = MarketingSolutionPage::query()->findOrFail($id);
        $data = $request->validate([
            'slug' => 'sometimes|string|max:255',
            'title' => 'sometimes|string|max:255',
            'body' => 'nullable|string',
            'published' => 'nullable|boolean',
        ]);
        $row->update($data);

        return response()->json(['data' => $row->fresh()]);
    }

    public function destroyPage(int $id): JsonResponse
    {
        MarketingSolutionPage::query()->findOrFail($id)->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }
}
