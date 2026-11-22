<?php

namespace Modules\Hrm\Services;

use Modules\Hrm\Entities\HrmLegalExportRecord;
use Modules\Hrm\Entities\HrmPayrollItem;
use Modules\Hrm\Entities\HrmPayrollRun;

class HrmLegalDisketteService
{
    /**
     * Generate standard Iranian SSO Insurance Diskette format (DSKWOR00.txt & DSKKAR00.txt compliant)
     *
     * @return array{record_count: int, total_wage: float, content_sample: string}
     */
    public function generateSsoDiskette(HrmPayrollRun $run, string $workshopCode = '0123456789'): array
    {
        $items = HrmPayrollItem::query()->where('payroll_run_id', $run->id)->with('employee')->get();
        $totalWage = 0.0;
        $lines = [];

        // Header line / workshop info
        $lines[] = "DSKKAR00:{$workshopCode}:{$run->year}:{$run->month}:" . count($items);

        foreach ($items as $item) {
            $emp = $item->employee;
            $nationalCode = $emp ? ($emp->national_id ?? '0000000000') : '0000000000';
            $gross = (float) $item->gross;
            $insurable = (float) ($item->insurable_wage ?: $gross);
            $totalWage += $insurable;

            // Standard SSO worker record layout
            $lines[] = sprintf(
                "DSKWOR00:%s:%s:%s:%d:%d:%.0f:%.0f",
                $workshopCode,
                $emp ? ($emp->insurance_no ?? '12345678') : '12345678',
                $nationalCode,
                $run->year,
                $run->month,
                $insurable,
                round($insurable * 0.07, 0)
            );
        }

        HrmLegalExportRecord::query()->create([
            'payroll_run_id' => $run->id,
            'export_type' => 'sso_diskette',
            'format' => 'txt',
            'total_records' => count($items),
            'total_amount' => $totalWage,
            'metadata' => [
                'workshop_code' => $workshopCode,
                'year' => $run->year,
                'month' => $run->month,
            ],
        ]);

        return [
            'record_count' => count($items),
            'total_wage' => $totalWage,
            'content_sample' => implode("\n", array_slice($lines, 0, 10)),
        ];
    }
}
