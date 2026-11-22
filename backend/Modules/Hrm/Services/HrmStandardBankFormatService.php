<?php

namespace Modules\Hrm\Services;

use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmPayrollItem;
use Modules\Hrm\Entities\HrmPayrollRun;

class HrmStandardBankFormatService
{
    /**
     * Generate official Iranian Interbank Payroll Transfer file (پایا / ساتنا)
     * Format: SHEBA (IBAN), Amount (Rials), Employee Full Name, National ID, Tracking Reference.
     * Compatible with Bank Melli, Mellat, Tejarat, Pasargad, Saman.
     *
     * @return array{
     *   total_records: int,
     *   total_amount: float,
     *   paya_lines: array<int, string>,
     *   file_content: string
     * }
     */
    public function generatePayaPaymentFile(HrmPayrollRun $run, string $companySheba = 'IR000000000000000000000000'): array
    {
        $items = HrmPayrollItem::query()->where('payroll_run_id', $run->id)->with('employee.profile')->get();
        $totalAmount = 0.0;
        $lines = [];

        // Header: PAYA|CompanySHEBA|Year|Month|RecordCount|TotalAmount
        $header = sprintf("PAYA_BATCH|%s|%04d%02d|%d", $companySheba, (int) $run->year, (int) $run->month, count($items));
        $lines[] = $header;

        foreach ($items as $item) {
            $emp = $item->employee;
            $profile = $emp?->profile;

            $sheba = (string) ($profile?->sheba ?? $emp?->bank_sheba ?? 'IR000000000000000000000000');
            $sheba = preg_replace('/[^A-Z0-9]/', '', strtoupper($sheba));
            if (! str_starts_with($sheba, 'IR')) {
                $sheba = 'IR' . str_pad($sheba, 24, '0', STR_PAD_LEFT);
            }

            $netAmount = (float) $item->net;
            $totalAmount += $netAmount;

            $fullName = ($emp?->first_name . ' ' . $emp?->last_name) ?: 'پرسنل';
            $nationalId = str_pad((string) ($emp?->national_id ?? '0000000000'), 10, '0', STR_PAD_LEFT);
            $refId = 'SAL-' . $run->year . $run->month . '-' . ($emp?->id ?? '0');

            // Format: SHEBA,Amount,FullName,NationalID,RefID
            $lines[] = sprintf("%s,%.0f,%s,%s,%s", $sheba, $netAmount, $fullName, $nationalId, $refId);
        }

        return [
            'total_records' => count($items),
            'total_amount' => $totalAmount,
            'paya_lines' => $lines,
            'file_content' => implode("\r\n", $lines),
        ];
    }
}
