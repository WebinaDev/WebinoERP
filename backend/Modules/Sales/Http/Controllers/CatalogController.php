<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Api\PaginatesApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Sales\Entities\SalesCatalogItem;

class CatalogController extends Controller
{
    use PaginatesApi;

    public function index(Request $request): JsonResponse
    {
        $query = SalesCatalogItem::query();
        $paginator = $this->applyIndexQuery(
            $query,
            $request,
            ['status' => 'status'],
            ['name', 'sku'],
            ['name', 'created_at', 'price'],
            'name',
            'asc',
        );

        return $this->paginatedResponse($paginator);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100|unique:sales_catalog_items,sku',
            'price' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'status' => 'nullable|string|max:20',
        ]);
        $item = SalesCatalogItem::create($data);

        return response()->json(['data' => $item, 'message' => 'Created'], 201);
    }

    public function show(SalesCatalogItem $catalog): JsonResponse
    {
        return response()->json(['data' => $catalog]);
    }

    public function update(Request $request, SalesCatalogItem $catalog): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'sku' => 'nullable|string|max:100|unique:sales_catalog_items,sku,'.$catalog->id,
            'price' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'status' => 'nullable|string|max:20',
        ]);
        $catalog->update($data);

        return response()->json(['data' => $catalog->fresh(), 'message' => 'Updated']);
    }

    public function destroy(SalesCatalogItem $catalog): JsonResponse
    {
        $catalog->delete();

        return response()->noContent();
    }
}
