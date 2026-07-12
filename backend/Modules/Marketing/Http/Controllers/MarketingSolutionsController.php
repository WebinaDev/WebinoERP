<?php

namespace Modules\Marketing\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\Marketing\Entities\MarketingMedia;
use Modules\Marketing\Entities\MarketingMediaFolder;
use Modules\Marketing\Entities\MarketingPage;
use Modules\Marketing\Entities\MarketingSiteSetting;
use Modules\Marketing\Http\Controllers\Concerns\HandlesMarketingCrud;

class MarketingSolutionsController extends Controller
{
    public function industries(): JsonResponse
    {
        $rows = \Modules\Marketing\Entities\MarketingSolutionIndustry::query()
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
        $row = \Modules\Marketing\Entities\MarketingSolutionIndustry::query()->create($data);

        return response()->json(['data' => $row], 201);
    }

    public function updateIndustry(Request $request, int $id): JsonResponse
    {
        $row = \Modules\Marketing\Entities\MarketingSolutionIndustry::query()->findOrFail($id);
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
        \Modules\Marketing\Entities\MarketingSolutionIndustry::query()->findOrFail($id)->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function pages(int $industryId): JsonResponse
    {
        $pages = \Modules\Marketing\Entities\MarketingSolutionPage::query()
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
        $row = \Modules\Marketing\Entities\MarketingSolutionPage::query()->create($data + ['industry_id' => $industryId]);

        return response()->json(['data' => $row], 201);
    }

    public function updatePage(Request $request, int $id): JsonResponse
    {
        $row = \Modules\Marketing\Entities\MarketingSolutionPage::query()->findOrFail($id);
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
        \Modules\Marketing\Entities\MarketingSolutionPage::query()->findOrFail($id)->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }
}
