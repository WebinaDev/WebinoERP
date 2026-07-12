<?php

namespace Modules\Marketplace\Http\Controllers;

use App\Http\Controllers\Api\PaginatesApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Marketplace\Entities\MarketplaceProduct;

class ProductController extends Controller
{
    use PaginatesApi;

    public function index(Request $request): JsonResponse
    {
        $query = MarketplaceProduct::query()->with('category')->orderBy('name');

        return $this->paginatedResponse($query->paginate($this->perPage($request)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:100|unique:marketplace_products,slug',
            'price' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:marketplace_categories,id',
            'status' => 'nullable|string|max:20',
        ]);
        $product = MarketplaceProduct::create($data);

        return response()->json(['data' => $product->load('category'), 'message' => 'Created'], 201);
    }

    public function show(MarketplaceProduct $product): JsonResponse
    {
        return response()->json(['data' => $product->load('category')]);
    }

    public function update(Request $request, MarketplaceProduct $product): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:100|unique:marketplace_products,slug,'.$product->id,
            'price' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:marketplace_categories,id',
            'status' => 'sometimes|string|max:20',
        ]);
        $product->update($data);

        return response()->json(['data' => $product->fresh('category'), 'message' => 'Updated']);
    }

    public function destroy(MarketplaceProduct $product): JsonResponse
    {
        $product->delete();

        return response()->noContent();
    }
}
