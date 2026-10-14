<?php

namespace Modules\Mfg\Services;

use Illuminate\Support\Facades\DB;
use Modules\Mfg\Entities\MfgWorkOrder;
use Modules\Mfg\Entities\MfgWorkOrderOperation;
use Modules\Scm\Entities\ScmWarehouse;
use Modules\Scm\Services\WarehouseService;

class MfgWorkOrderService
{
    public function __construct(private WarehouseService $warehouse) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $operations
     */
    public function create(array $data, array $operations = []): MfgWorkOrder
    {
        return DB::transaction(function () use ($data, $operations) {
            $wo = MfgWorkOrder::query()->create(array_merge($data, ['status' => 'draft']));
            $this->syncOperations($wo, $operations);

            return $wo->load(['bom.lines', 'operations']);
        });
    }

    /**
     * @param  list<array<string, mixed>>  $operations
     */
    public function syncOperations(MfgWorkOrder $wo, array $operations): void
    {
        MfgWorkOrderOperation::query()->where('work_order_id', $wo->id)->delete();
        foreach ($operations as $i => $row) {
            MfgWorkOrderOperation::query()->create([
                'work_order_id' => $wo->id,
                'sequence' => (int) ($row['sequence'] ?? ($i + 1)),
                'name' => (string) $row['name'],
                'status' => $row['status'] ?? 'pending',
                'duration_minutes' => isset($row['duration_minutes']) ? (int) $row['duration_minutes'] : null,
            ]);
        }
    }

    public function release(MfgWorkOrder $wo): MfgWorkOrder
    {
        if ($wo->status !== 'draft') {
            abort(422, __('messages.only_draft_work_orders_can_be_released'));
        }
        $wo->update(['status' => 'released']);

        return $wo->fresh();
    }

    public function start(MfgWorkOrder $wo): MfgWorkOrder
    {
        if (! in_array($wo->status, ['released', 'draft'], true)) {
            abort(422, __('messages.work_order_cannot_be_started_from_current_status'));
        }
        $wo->update(['status' => 'in_progress']);

        return $wo->fresh();
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function complete(MfgWorkOrder $wo, array $options = []): MfgWorkOrder
    {
        if (! in_array($wo->status, ['in_progress', 'released'], true)) {
            abort(422, __('messages.work_order_cannot_be_completed_from_current_status'));
        }

        $qtyProduced = isset($options['qty_produced'])
            ? (float) $options['qty_produced']
            : (float) $wo->qty_planned;

        return DB::transaction(function () use ($wo, $options, $qtyProduced) {
            if (! empty($options['consume_materials']) && $wo->warehouse_id && $wo->bom_id) {
                $this->consumeMaterials($wo, $qtyProduced);
            }

            $wo->update([
                'status' => 'completed',
                'qty_produced' => $qtyProduced,
            ]);

            return $wo->fresh()->load(['bom.lines', 'operations']);
        });
    }

    public function cancel(MfgWorkOrder $wo): MfgWorkOrder
    {
        if ($wo->status === 'completed') {
            abort(422, __('messages.completed_work_orders_cannot_be_cancelled'));
        }
        $wo->update(['status' => 'cancelled']);

        return $wo->fresh();
    }

    private function consumeMaterials(MfgWorkOrder $wo, float $qtyProduced): void
    {
        if (! ScmWarehouse::query()->whereKey($wo->warehouse_id)->exists()) {
            return;
        }

        $wo->loadMissing('bom.lines');
        $bom = $wo->bom;
        if (! $bom) {
            return;
        }

        $outItems = [];
        foreach ($bom->lines as $line) {
            $need = (float) $line->quantity * $qtyProduced * (1 + ((float) $line->scrap_percent / 100));
            if ($need > 0) {
                $outItems[] = ['product_id' => $line->component_product_id, 'quantity' => $need];
            }
        }

        if ($outItems !== []) {
            $out = $this->warehouse->createDocument([
                'warehouse_id' => $wo->warehouse_id,
                'reference' => 'WO-'.$wo->id,
                'items' => $outItems,
            ], 'outbound', null);
            $this->warehouse->postDocument((int) $out['id'], 'outbound', false);
        }

        $in = $this->warehouse->createDocument([
            'warehouse_id' => $wo->warehouse_id,
            'reference' => 'WO-'.$wo->id,
            'items' => [['product_id' => $wo->product_id, 'quantity' => $qtyProduced]],
        ], 'inbound', null);
        $this->warehouse->postDocument((int) $in['id'], 'inbound', true);
    }
}
