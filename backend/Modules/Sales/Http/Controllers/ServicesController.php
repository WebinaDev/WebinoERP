<?php

namespace Modules\Sales\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Docs\Entities\DocsContract;
use Modules\Projects\Entities\PrjTaskTemplate;
use Modules\Sales\Entities\SalesCatalogItem;

class ServicesController extends Controller
{
    public function subscriptions(): JsonResponse
    {
        $items = SalesCatalogItem::query()
            ->where('type', 'subscription')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $items]);
    }

    public function products(): JsonResponse
    {
        $items = SalesCatalogItem::query()
            ->where(function ($q) {
                $q->where('type', 'product')->orWhereNull('type');
            })
            ->orderBy('name')
            ->get()
            ->map(function (SalesCatalogItem $item) {
                $meta = $item->meta ?? [];

                return array_merge($item->toArray(), [
                    'task_template_id' => $meta['task_template_id'] ?? null,
                    'service_task_type' => $meta['service_task_type'] ?? 'onetime',
                    'task_template_title' => $meta['task_template_title'] ?? null,
                ]);
            });

        return response()->json(['data' => $items]);
    }

    public function taskTemplates(): JsonResponse
    {
        if (! class_exists(PrjTaskTemplate::class)) {
            return response()->json(['data' => []]);
        }

        return response()->json([
            'data' => PrjTaskTemplate::query()->orderByDesc('id')->limit(200)->get(),
        ]);
    }

    public function updateProductTaskTemplate(Request $request, SalesCatalogItem $catalog): JsonResponse
    {
        $data = $request->validate([
            'task_template_id' => 'nullable|integer|min:0',
            'service_task_type' => 'nullable|string|max:50',
        ]);

        $meta = $catalog->meta ?? [];
        $meta['task_template_id'] = $data['task_template_id'] ?: null;
        $meta['service_task_type'] = $data['service_task_type'] ?? 'onetime';

        if (! empty($meta['task_template_id']) && class_exists(PrjTaskTemplate::class)) {
            $tpl = PrjTaskTemplate::query()->find($meta['task_template_id']);
            $meta['task_template_title'] = $tpl?->title ?? $tpl?->name ?? null;
        } else {
            $meta['task_template_title'] = null;
        }

        $catalog->update(['meta' => $meta]);

        return response()->json(['data' => $catalog->fresh(), 'message' => 'Updated']);
    }

    public function convertContract(Request $request, SalesCatalogItem $catalog): JsonResponse
    {
        $contract = DocsContract::query()->create([
            'title' => $catalog->name,
            'party_name' => null,
            'status' => 'draft',
            'body' => $catalog->description,
            'meta' => [
                'source' => 'catalog_convert',
                'catalog_id' => $catalog->id,
                'amount' => $catalog->price,
                'sku' => $catalog->sku,
            ],
            'created_by' => $request->user()->id,
        ]);

        $meta = $catalog->meta ?? [];
        $meta['converted_contract_id'] = $contract->id;
        $catalog->update(['meta' => $meta]);

        return response()->json([
            'data' => [
                'subscription_id' => $catalog->id,
                'contract_id' => $contract->id,
                'status' => 'created',
            ],
            'message' => 'Contract created from catalog item',
        ], 201);
    }
}
