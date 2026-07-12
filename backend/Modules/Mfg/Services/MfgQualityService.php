<?php

namespace Modules\Mfg\Services;

use Illuminate\Support\Facades\DB;
use Modules\Mfg\Entities\MfgQualityCheckItem;
use Modules\Mfg\Entities\MfgQualityInspection;

class MfgQualityService
{
    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $checkItems
     */
    public function create(array $data, array $checkItems = []): MfgQualityInspection
    {
        return DB::transaction(function () use ($data, $checkItems) {
            $inspection = MfgQualityInspection::query()->create(array_merge($data, [
                'status' => 'open',
            ]));
            $this->syncCheckItems($inspection, $checkItems);

            return $inspection->load('checkItems');
        });
    }

    /**
     * @param  list<array<string, mixed>>  $checkItems
     */
    public function syncCheckItems(MfgQualityInspection $inspection, array $checkItems): void
    {
        MfgQualityCheckItem::query()->where('inspection_id', $inspection->id)->delete();
        foreach ($checkItems as $row) {
            MfgQualityCheckItem::query()->create([
                'inspection_id' => $inspection->id,
                'criterion' => (string) $row['criterion'],
                'measured_value' => $row['measured_value'] ?? null,
                'spec_min' => $row['spec_min'] ?? null,
                'spec_max' => $row['spec_max'] ?? null,
                'passed' => $row['passed'] ?? null,
            ]);
        }
    }

    public function complete(MfgQualityInspection $inspection): MfgQualityInspection
    {
        $inspection->load('checkItems');
        $allPass = true;
        foreach ($inspection->checkItems as $item) {
            if ($item->passed === false) {
                $allPass = false;
                break;
            }
            if ($item->passed === null && $item->spec_min !== null && $item->spec_max !== null && $item->measured_value !== null) {
                $val = (float) $item->measured_value;
                if ($val < (float) $item->spec_min || $val > (float) $item->spec_max) {
                    $allPass = false;
                    $item->update(['passed' => false]);
                } else {
                    $item->update(['passed' => true]);
                }
            }
        }

        $inspection->update([
            'status' => 'completed',
            'result' => $allPass ? 'pass' : 'fail',
            'inspected_at' => now(),
        ]);

        return $inspection->fresh()->load('checkItems');
    }
}
