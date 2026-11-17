<?php

namespace Modules\Qm\Services;

use Modules\Qm\Entities\QmLotBatch;

class QualityGateService
{
    /**
     * Strict check: Can inventory of this lot/product move into sellable or shippable status?
     */
    public function isStockClearedForMovement(?int $lotId, ?int $productId = null): bool
    {
        if (! $lotId) {
            return true;
        }

        $lot = QmLotBatch::query()->find($lotId);
        if (! $lot) {
            return true;
        }

        return $lot->status === 'released';
    }

    public function releaseLot(QmLotBatch $lot): QmLotBatch
    {
        $lot->update(['status' => 'released']);

        return $lot;
    }

    public function quarantineLot(QmLotBatch $lot, ?string $reason = null): QmLotBatch
    {
        $lot->update([
            'status' => 'quarantined',
            'notes' => $reason ? ($lot->notes ? "{$lot->notes} | {$reason}" : $reason) : $lot->notes,
        ]);

        return $lot;
    }
}
