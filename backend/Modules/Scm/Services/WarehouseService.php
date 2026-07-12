<?php

namespace Modules\Scm\Services;

use Illuminate\Support\Facades\DB;
use Modules\Scm\Entities\ScmWarehouse;
use Modules\Scm\Entities\ScmWarehouseDocument;
use Modules\Scm\Entities\ScmWarehouseStock;

class WarehouseService
{
    public function updateWarehouse(array $p): array
    {
        $data = validator($p, [
            'id' => 'required|exists:scm_warehouses,id',
            'name' => 'sometimes|string|max:191',
            'address' => 'nullable|string',
            'is_default' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ])->validate();
        $w = ScmWarehouse::query()->findOrFail($data['id']);
        $w->update(collect($data)->except('id')->all());

        return ['warehouse' => $w->fresh()];
    }

    public function deleteWarehouse(int $id): array
    {
        ScmWarehouse::query()->whereKey($id)->delete();

        return ['deleted' => true];
    }

    public function createDocument(array $p, string $type, ?int $userId): array
    {
        $data = validator($p, [
            'warehouse_id' => 'required|exists:scm_warehouses,id',
            'number' => 'nullable|string|max:50',
            'document_date' => 'nullable|date',
            'reference' => 'nullable|string|max:191',
            'notes' => 'nullable|string',
            'items' => 'nullable|array',
        ])->validate();

        $doc = ScmWarehouseDocument::query()->create([
            'type' => $type,
            'warehouse_id' => $data['warehouse_id'],
            'number' => $data['number'] ?? null,
            'document_date' => $data['document_date'] ?? now()->toDateString(),
            'status' => 'draft',
            'reference' => $data['reference'] ?? null,
            'items' => $data['items'] ?? [],
            'notes' => $data['notes'] ?? null,
            'created_by' => $userId,
        ]);

        return ['id' => $doc->id, 'document' => $doc];
    }

    public function getDocument(int $id, string $type): array
    {
        $doc = ScmWarehouseDocument::query()->where('type', $type)->findOrFail($id);

        return ['document' => $doc];
    }

    public function postDocument(int $id, string $type, bool $inbound): array
    {
        $doc = ScmWarehouseDocument::query()->where('type', $type)->findOrFail($id);

        return DB::transaction(function () use ($doc, $inbound) {
            $doc->update(['status' => 'posted']);
            foreach ($doc->items ?? [] as $row) {
                $pid = (int) ($row['product_id'] ?? 0);
                $qty = (float) ($row['quantity'] ?? 0);
                if (! $pid || $qty <= 0) {
                    continue;
                }
                $stock = ScmWarehouseStock::query()->firstOrCreate(
                    ['warehouse_id' => $doc->warehouse_id, 'product_id' => $pid],
                    ['quantity' => 0, 'reorder_point' => null]
                );
                $delta = $inbound ? $qty : -$qty;
                if (! $inbound && (float) $stock->quantity + $delta < 0) {
                    throw new \RuntimeException('Insufficient stock for product '.$pid);
                }
                $stock->update(['quantity' => (float) $stock->quantity + $delta]);
            }

            return ['posted' => true, 'document' => $doc->fresh()];
        });
    }

    public function createAudit(array $p, ?int $userId): array
    {
        return $this->createDocument($p, 'audit', $userId);
    }

    public function recordAuditItem(array $p): array
    {
        $data = validator($p, [
            'document_id' => 'required|exists:scm_warehouse_documents,id',
            'product_id' => 'required|exists:acc_products,id',
            'counted' => 'required|numeric|min:0',
        ])->validate();
        $doc = ScmWarehouseDocument::query()->where('type', 'audit')->findOrFail($data['document_id']);
        $items = $doc->items ?? [];
        foreach ($items as &$it) {
            if ((int) ($it['product_id'] ?? 0) === (int) $data['product_id']) {
                $it['counted'] = (float) $data['counted'];
                break;
            }
        }
        unset($it);
        $doc->update(['items' => $items]);

        return ['document' => $doc->fresh()];
    }

    public function completeAudit(int $documentId): array
    {
        $doc = ScmWarehouseDocument::query()->where('type', 'audit')->findOrFail($documentId);
        $doc->update(['status' => 'completed']);

        return ['document' => $doc->fresh()];
    }

    public function postAudit(int $id): array
    {
        return DB::transaction(function () use ($id) {
            $doc = ScmWarehouseDocument::query()->where('type', 'audit')->findOrFail($id);
            foreach ($doc->items ?? [] as $row) {
                $pid = (int) ($row['product_id'] ?? 0);
                $counted = isset($row['counted']) ? (float) $row['counted'] : null;
                if (! $pid || $counted === null) {
                    continue;
                }
                $stock = ScmWarehouseStock::query()->firstOrCreate(
                    ['warehouse_id' => $doc->warehouse_id, 'product_id' => $pid],
                    ['quantity' => 0, 'reorder_point' => null]
                );
                $stock->update(['quantity' => $counted]);
            }
            $doc->update(['status' => 'posted']);

            return ['posted' => true, 'document' => $doc->fresh()];
        });
    }

    public function stockForProduct(int $warehouseId, int $productId): array
    {
        $stock = ScmWarehouseStock::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->first();

        return ['stock' => $stock];
    }
}
