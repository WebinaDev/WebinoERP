<?php

namespace Modules\Marketing\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Marketing\Entities\MarketingMenu;
use Modules\Marketing\Entities\MarketingMenuItem;

class MarketingMenusController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = MarketingMenu::query()->with('items')->orderBy('location')->get();

        return response()->json(['data' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'location' => 'required|string|max:32|unique:marketing_menus,location',
            'name' => 'required|string|max:255',
            'published' => 'nullable|boolean',
        ]);
        $row = MarketingMenu::query()->create($data);

        return response()->json(['data' => $row], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $row = MarketingMenu::query()->findOrFail($id);
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'published' => 'sometimes|boolean',
        ]);
        $row->update($data);

        return response()->json(['data' => $row->fresh('items')]);
    }

    public function destroy(int $id): JsonResponse
    {
        MarketingMenu::query()->findOrFail($id)->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function storeItem(Request $request, int $id): JsonResponse
    {
        MarketingMenu::query()->findOrFail($id);
        $data = $request->validate([
            'parent_id' => 'nullable|exists:marketing_menu_items,id',
            'label' => 'required|string|max:255',
            'label_en' => 'nullable|string|max:255',
            'href' => 'required|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'published' => 'nullable|boolean',
        ]);
        $item = MarketingMenuItem::query()->create($data + ['menu_id' => $id]);

        return response()->json(['data' => $item], 201);
    }

    public function updateItem(Request $request, int $itemId): JsonResponse
    {
        $item = MarketingMenuItem::query()->findOrFail($itemId);
        $data = $request->validate([
            'parent_id' => 'nullable|exists:marketing_menu_items,id',
            'label' => 'sometimes|string|max:255',
            'label_en' => 'nullable|string|max:255',
            'href' => 'sometimes|string|max:255',
            'sort_order' => 'sometimes|integer|min:0',
            'published' => 'sometimes|boolean',
        ]);
        $item->update($data);

        return response()->json(['data' => $item->fresh()]);
    }

    public function destroyItem(int $itemId): JsonResponse
    {
        MarketingMenuItem::query()->findOrFail($itemId)->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }
}
