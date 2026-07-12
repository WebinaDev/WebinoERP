<?php

namespace Modules\Projects\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Projects\Entities\CatalogProduct;
use Modules\Projects\Entities\Contract;

class SubscriptionController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = CatalogProduct::query()
            ->orderBy('name')
            ->get()
            ->map(fn (CatalogProduct $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'amount' => (float) $p->price,
                'source' => 'catalog_product',
            ]);

        return response()->json([
            'data' => $rows,
            'message' => 'Catalog products (subscription-style parity when WooCommerce is absent)',
        ]);
    }

    public function convert(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'title' => 'nullable|string|max:255',
            'amount' => 'nullable|numeric',
            'customer_account_id' => 'nullable|exists:crm_accounts,id',
        ]);
        $c = Contract::query()->create([
            'title' => $data['title'] ?? ('اشتراک #'.$id),
            'amount' => $data['amount'] ?? 0,
            'status' => 'draft',
            'customer_account_id' => $data['customer_account_id'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['data' => ['contract_id' => $c->id, 'contract' => $c]], 201);
    }
}
