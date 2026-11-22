<?php

namespace Modules\Hrm\Services;

use InvalidArgumentException;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmPayrollItem;
use Modules\Hrm\Entities\HrmPayrollRun;
use Modules\Hrm\Entities\HrmPayrollSetting;

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

        $rearranged = substr($clean, 4) . substr($clean, 0, 4);

        $numericStr = '';
        for ($i = 0; $i < strlen($rearranged); $i++) {
            $char = $rearranged[$i];
            $numericStr .= is_numeric($char) ? $char : (ord($char) - ord('A') + 10);
        }

        $remainder = 0;
        foreach (str_split($numericStr, 7) as $chunk) {
            $remainder = ($remainder . $chunk) % 97;
        }

        return $remainder === 1;
    }

    /**
     * Resolve company payment account/IBAN from enterprise settings.
     */
    private function resolveCompanySheba(?string $provided = null): string
    {
        $sheba = trim((string) $provided);
        if ($sheba !== '') {
            return $sheba;
        }

        $setting = HrmPayrollSetting::query()->where('key', 'company_sheba')->first();
        $sheba = trim((string) ($setting?->value ?? ''));

        if ($sheba === '' || ! $this->isValidSheba($sheba)) {
            throw new InvalidArgumentException('شماره شبای مبدأ شرکت در تنظیمات سیستم حقوق تعریف نشده است.');
        }

        return $sheba;
    }

    /**
     * Generate multi-bank payroll transfer file with strict SHEBA validation and rejection of empty/dummy data.
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
    public function generateBankExport(HrmPayrollRun $run, string $format = 'paya_standard', ?string $companyAccount = null): array
    {
        $companySheba = $this->resolveCompanySheba($companyAccount);
        $items = HrmPayrollItem::query()->where('payroll_run_id', $run->id)->with('employee.profile')->get();

        if ($items->isEmpty()) {
            throw new InvalidArgumentException('رکوردی در این دوره حقوق برای واریز بانکی وجود ندارد.');
        }

        $totalAmount = 0.0;
        $validSheba = 0;
        $invalidSheba = 0;
        $lines = [];

        $batchId = sprintf("PAYA%04d%02d%04d", $run->year, $run->month, $run->id);
        $lines[] = sprintf("// BATCH: %s | FORMAT: %s | SOURCE_SHEBA: %s | CREATED: %s", $batchId, strtoupper($format), $companySheba, now()->toIso8601String());

        foreach ($items as $item) {
            $emp = $item->employee;
            $profile = $emp?->profile;

            $fullName = trim(($emp?->first_name . ' ' . $emp?->last_name) ?: '');
            if ($fullName === '') {
                throw new InvalidArgumentException("نام و نام خانوادگی کارمند شناسه #{$emp?->id} نامعتبر است.");
            }

            $nationalId = trim((string) ($profile?->national_id ?? $emp?->national_id ?? ''));
            if (! \Modules\Hrm\Support\IranianNationalId::isValid($nationalId)) {
                throw new InvalidArgumentException("کد ملی کارمند ({$fullName}) نامعتبر است. صدور فایل پرداخت متوقف گردید.");
            }

            $rawSheba = trim((string) ($profile?->sheba ?? $emp?->bank_sheba ?? ''));
            if ($rawSheba === '' || ! $this->isValidSheba($rawSheba)) {
                throw new InvalidArgumentException("شماره شبای بانکی کارمند ({$fullName}) نامعتبر است ({$rawSheba}).");
            }

            $validSheba++;
            $net = (float) $item->net;
            $totalAmount += $net;

            if ($format === 'mellat_batch') {
                $lines[] = sprintf("%s,%.0f,%s,%s,%s", $rawSheba, $net, $fullName, $nationalId, "حقوق ماه {$run->month}");
            } else {
                $ref = sprintf("%04d%02d-%s", $run->year, $run->month, $emp?->id ?? '0');
                $lines[] = sprintf("%s,%.0f,%s,%s,%s", $rawSheba, $net, $fullName, $nationalId, $ref);
            }
        }

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
