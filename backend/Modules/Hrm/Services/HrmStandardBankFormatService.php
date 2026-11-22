<?php

namespace Modules\Hrm\Services;

use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmPayrollItem;
use Modules\Hrm\Entities\HrmPayrollRun;

class HrmStandardBankFormatService
{
    /**
     * Validate Iranian SHEBA (IBAN) using ISO 7064 Mod 97-10 algorithm.
     */
    public function isValidSheba(string $sheba): bool
    {
        $clean = strtoupper(preg_replace('/[^A-Z0-9]/', '', $sheba));
        if (strlen($clean) !== 26 || ! str_starts_with($clean, 'IR')) {
            return false;
        }

        // Rearrange: move first 4 chars to the end
        $rearranged = substr($clean, 4) . substr($clean, 0, 4);

        // Convert letters to numbers: I = 18, R = 27
        $numericStr = '';
        for ($i = 0; $i < strlen($rearranged); $i++) {
            $char = $rearranged[$i];
            $numericStr .= is_numeric($char) ? $char : (ord($char) - ord('A') + 10);
        }

        // Mod 97 check
        $remainder = 0;
        foreach (str_split($numericStr, 7) as $chunk) {
            $remainder = ($remainder . $chunk) % 97;
        }

        return $remainder === 1;
    }

    /**
     * Generate multi-bank payroll transfer file:
     * - paya_standard: Central Bank of Iran ACH/PAYA standard
     * - mellat_batch: Bank Mellat multi-account payment file
     * - melli_batch: Bank Melli BMI payroll transfer format
     *
     * @return array{
     *   bank_format: string,
     *   total_records: int,
     *   total_amount: float,
     *   valid_sheba_count: int,
     *   invalid_sheba_count: int,
     *   checksum: string,
     *   file_content: string
     * }
     */
    public function generateBankExport(HrmPayrollRun $run, string $format = 'paya_standard', string $companyAccount = 'IR000000000000000000000000'): array
    {
        $items = HrmPayrollItem::query()->where('payroll_run_id', $run->id)->with('employee.profile')->get();
        $totalAmount = 0.0;
        $validSheba = 0;
        $invalidSheba = 0;
        $lines = [];

        // Header with timestamp and batch metadata
        $batchId = sprintf("PAYA%04d%02d%04d", $run->year, $run->month, $run->id);
        $lines[] = sprintf("// WEBINO-ERP PAYROLL BATCH: %s | FORMAT: %s | CREATED: %s", $batchId, strtoupper($format), now()->toIso8601String());

        foreach ($items as $item) {
            $emp = $item->employee;
            $profile = $emp?->profile;

            $rawSheba = (string) ($profile?->sheba ?? $emp?->bank_sheba ?? 'IR000000000000000000000000');
            $cleanSheba = strtoupper(preg_replace('/[^A-Z0-9]/', '', $rawSheba));
            if (! str_starts_with($cleanSheba, 'IR')) {
                $cleanSheba = 'IR' . str_pad($cleanSheba, 24, '0', STR_PAD_LEFT);
            }

            if ($this->isValidSheba($cleanSheba)) {
                $validSheba++;
            } else {
                $invalidSheba++;
            }

            $net = (float) $item->net;
            $totalAmount += $net;

            $fullName = trim(($emp?->first_name . ' ' . $emp?->last_name) ?: 'پرسنل');
            $nationalId = str_pad((string) ($emp?->national_id ?? '0000000000'), 10, '0', STR_PAD_LEFT);

            if ($format === 'mellat_batch') {
                // Bank Mellat specific layout: Account/IBAN,Amount,Name,Ref,Description
                $lines[] = sprintf("%s,%.0f,%s,%s,%s", $cleanSheba, $net, $fullName, $nationalId, "حقوق ماه {$run->month}");
            } else {
                // Standard Paya layout: SHEBA,Amount,FullName,NationalID,RefCode
                $ref = sprintf("%04d%02d-%s", $run->year, $run->month, $emp?->id ?? '0');
                $lines[] = sprintf("%s,%.0f,%s,%s,%s", $cleanSheba, $net, $fullName, $nationalId, $ref);
            }
        }

        // Summary footer
        $lines[] = sprintf("// SUMMARY: COUNT=%d | TOTAL_AMOUNT=%.0f | VALID_SHEBA=%d", count($items), $totalAmount, $validSheba);

        $content = implode("\r\n", $lines);
        $checksum = hash('sha256', $content);

        return [
            'bank_format' => $format,
            'total_records' => count($items),
            'total_amount' => $totalAmount,
            'valid_sheba_count' => $validSheba,
            'invalid_sheba_count' => $invalidSheba,
            'checksum' => $checksum,
            'file_content' => $content,
        ];
    }
}
