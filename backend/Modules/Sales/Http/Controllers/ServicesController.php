<?php

namespace Modules\Sales\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Sales\Entities\SalesCatalogItem;
use Modules\Sales\Entities\SalesInvoice;

class ServicesController extends Controller
{
    public function subscriptions(): JsonResponse
    {
        $items = SalesCatalogItem::query()->orderBy('name')->get();

        return response()->json(['data' => $items]);
    }

    public function products(): JsonResponse
    {
        $items = SalesCatalogItem::query()->orderBy('name')->get();

        return response()->json(['data' => $items]);
    }

    public function taskTemplates(): JsonResponse
    {
        return response()->json(['data' => []]);
    }

    public function convertContract(Request $request, SalesCatalogItem $catalog): JsonResponse
    {
        return response()->json([
            'data' => ['subscription_id' => $catalog->id, 'contract_id' => null, 'status' => 'queued'],
            'message' => 'Convert contract queued',
        ]);
    }
}
