<?php

namespace Modules\Hrm\Services;

use Modules\Hrm\Entities\HrmPayrollRun;
use Modules\Hrm\Entities\HrmPayrollItem;

class HrmMultiBankExportEngine
{
    public function __construct(
        private readonly HrmStandardBankFormatService $standardService
    ) {}

    /**
     * Supported bank codes:
     * - paya_standard: پایا استاندارد بانک مرکزی
     * - satna_rtgs: ساتنا مبالغ کلان و فوری
     * - melli_bmi: بانک ملی ایران (شناسه شبای مقصد + کد ملی + شناسه پرداخت)
     * - mellat_batch: بانک ملت (قالب فایل پرداخت گروهی شتابی)
     * - tejarat_batch: بانک تجارت (فرمت ستاره‌دار و کاما تفکیک‌شده)
     * - pasargad_batch: بانک پاسارگاد
     *
     * @return array{
     *   bank: string,
     *   record_count: int,
     *   total_amount: float,
     *   valid_sheba_count: int,
     *   file_content: string,
     *   checksum: string
     * }
     */
    public function generateCertifiedBankFile(HrmPayrollRun $run, string $bank = 'paya_standard'): array
    {
        $items = HrmPayrollItem::query()->where('payroll_run_id', $run->id)->with('employee.profile')->get();
        $lines = [];
        $totalAmount = 0.0;
        $validSheba = 0;

        // Header by Bank Protocol
        $dateStr = now()->format('Ymd');
        $batchRef = sprintf("%04d%02d-%03d", $run->year, $run->month, $run->id);

        if ($bank === 'satna_rtgs') {
            $lines[] = "SATNA_HEADER:DATE={$dateStr}:BATCH={$batchRef}:CURRENCY=IRR";
        } elseif ($bank === 'melli_bmi') {
            $lines[] = "BMI_PAYROLL_HDR,{$dateStr},{$batchRef}," . count($items);
        } else {
            $lines[] = "PAYA_ACH_HDR,{$dateStr},{$batchRef}," . count($items);
        }

        foreach ($items as $item) {
            $emp = $item->employee;
            $profile = $emp?->profile;

            $rawSheba = (string) ($profile?->sheba ?? $emp?->bank_sheba ?? 'IR000000000000000000000000');
            $cleanSheba = strtoupper(preg_replace('/[^A-Z0-9]/', '', $rawSheba));
            if (! str_starts_with($cleanSheba, 'IR')) {
                $cleanSheba = 'IR' . str_pad($cleanSheba, 24, '0', STR_PAD_LEFT);
            }

            if ($this->standardService->isValidSheba($cleanSheba)) {
                $validSheba++;
            }

            $net = (float) $item->net;
            $totalAmount += $net;

            $name = trim(($emp?->first_name . ' ' . $emp?->last_name) ?: 'پرسنل');
            $nationalId = str_pad((string) ($emp?->national_id ?? '0000000000'), 10, '0', STR_PAD_LEFT);

            $line = match ($bank) {
                'satna_rtgs' => sprintf("SATNA|%s|%.0f|%s|%s|%s", $cleanSheba, $net, $name, $nationalId, "واریز فوری حقوق {$run->month}"),
                'melli_bmi' => sprintf("%s,%.0f,%s,%s,SALARY_%04d%02d", $cleanSheba, $net, $name, $nationalId, $run->year, $run->month),
                'tejarat_batch' => sprintf("%s*%.0f*%s*%s*%s", $cleanSheba, $net, $name, $nationalId, $batchRef),
                'mellat_batch' => sprintf("%s,%.0f,%s,%s,حقوق ماه %d", $cleanSheba, $net, $name, $nationalId, $run->month),
                default => sprintf("%s,%.0f,%s,%s,%s", $cleanSheba, $net, $name, $nationalId, $batchRef),
            };

            $lines[] = $line;
        }

        $content = implode("\r\n", $lines);

        return [
            'bank' => $bank,
            'record_count' => count($items),
            'total_amount' => $totalAmount,
            'valid_sheba_count' => $validSheba,
            'file_content' => $content,
            'checksum' => hash('sha256', $content),
        ];
    }
}
