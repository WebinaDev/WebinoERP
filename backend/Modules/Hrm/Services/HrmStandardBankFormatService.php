<?php

namespace Modules\Hrm\Services;

use InvalidArgumentException;
use Modules\Hrm\Entities\HrmPayrollRun;
use Modules\Hrm\Entities\HrmPayrollSetting;
use Modules\Hrm\Entities\HrmSubsidiaryCompany;
use Modules\Hrm\Support\IranianNationalId;

class HrmStandardBankFormatService
{
    /**
     * Supported bank codes
     */
    public const SUPPORTED_BANKS = [
        'paya_standard' => 'سامانه پایا (ACH بانک مرکزی)',
        'satna_rtgs' => 'سامانه ساتنا (RTGS بانک مرکزی)',
        'melli_bmi' => 'بانک ملی ایران (سامانه بام/سیبا)',
        'mellat_batch' => 'بانک ملت (سامانه فرا/محبین)',
        'tejarat_batch' => 'بانک تجارت (فرمت ستاره‌دار/فراپایا)',
        'sepah_batch' => 'بانک سپه (سامانه امید/نگین)',
        'saderat_bsi' => 'بانک صادرات ایران (سپهر/سیمرغ)',
    ];

    /**
     * Resolve sending company SHEBA account from subsidiary company or organization settings.
     */
    public function resolveSourceSheba(?HrmPayrollRun $run = null): string
    {
        if ($run && $run->subsidiary_company_id && \Illuminate\Support\Facades\Schema::hasTable('hrm_subsidiary_companies')) {
            $company = HrmSubsidiaryCompany::query()->find($run->subsidiary_company_id);
            if ($company && ! empty($company->company_sheba) && $this->validateShebaMod97($company->company_sheba)) {
                return $company->company_sheba;
            }
        }

        $setting = HrmPayrollSetting::query()->where('key', 'source_sheba')->first();
        $sheba = trim((string) ($setting?->value ?? ''));

        if ($sheba === '' || ! $this->validateShebaMod97($sheba)) {
            throw new InvalidArgumentException('شماره شبای مبدأ پرداخت حقوق در تنظیمات سازمان تعریف نشده یا نامعتبر است.');
        }

        return $sheba;
    }

    /**
     * Validate Iranian IBAN / SHEBA via Mod-97-10 check (ISO 7064).
     */
    public function validateShebaMod97(string $sheba): bool
    {
        $clean = strtoupper(str_replace(' ', '', $sheba));
        if (strlen($clean) !== 26 || ! str_starts_with($clean, 'IR')) {
            return false;
        }

        // Check if digits are entirely zeroes (dummy SHEBA)
        $digits = substr($clean, 4);
        if (trim($digits, '0') === '') {
            return false;
        }

        $rearranged = substr($clean, 4) . '1827' . substr($clean, 2, 2);
        return bcmod($rearranged, '97') === '1';
    }

    /**
     * Alias for backward compatibility with existing tests.
     */
    public function isValidSheba(string $sheba): bool
    {
        return $this->validateShebaMod97($sheba);
    }

    /**
     * Pre-export strict validation of payroll run items for banking:
     * - Mandatory Sheba (with ISO 7064 Mod-97 validation)
     * - Positive payable net amount (> 0)
     * - Mandatory 10-digit National ID
     * - Mandatory Employee Full Name
     *
     * @return array{is_valid: bool, errors: list<string>, valid_count: int, total_amount: float}
     */
    public function validateBankExportIntegrity(HrmPayrollRun $run, ?string $companySheba = null): array
    {
        $run->loadMissing(['items.employee.profile']);
        $errors = [];
        $totalAmount = 0.0;
        $validCount = 0;

        // Verify source Sheba
        $source = $companySheba;
        if (empty($source)) {
            try {
                $source = $this->resolveSourceSheba($run);
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }

        if ($run->items->isEmpty()) {
            $errors[] = 'دوره حقوق فاقد هرگونه فیش یا رکورد جهت واریز بانکی است.';
            return [
                'is_valid' => false,
                'errors' => $errors,
                'valid_count' => 0,
                'total_amount' => 0.0,
            ];
        }

        foreach ($run->items as $idx => $item) {
            $line = $idx + 1;
            $emp = $item->employee;
            $profile = $emp?->profile;

            if (! $emp) {
                $errors[] = "ردیف {$line}: رکورد پرداخت فاقد کارمند متصل است.";
                continue;
            }

            $fullName = trim("{$emp->first_name} {$emp->last_name}");
            if (empty($emp->first_name) || empty($emp->last_name)) {
                $errors[] = "ردیف {$line}: نام و نام خانوادگی کارمند الزامی و خالی است.";
            }

            // Strict National ID
            $nationalId = trim((string) ($profile?->national_id ?? $emp->national_id ?? ''));
            if (! IranianNationalId::isValid($nationalId)) {
                $errors[] = "ردیف {$line} ({$fullName}): کد ملی ۱۰ رقمی الزامی و نامعتبر است ({$nationalId}).";
            }

            // Strict Sheba
            $sheba = trim((string) ($emp->sheba_number ?: ($profile?->sheba ?? $emp->bank_sheba ?? '')));
            if (empty($sheba) || ! $this->validateShebaMod97($sheba)) {
                $errors[] = "ردیف {$line} ({$fullName}): شماره شبای بانکی الزامی و نامعتبر است ({$sheba}).";
            }

            // Positive net amount
            $net = (float) $item->net;
            if ($net <= 0) {
                $errors[] = "ردیف {$line} ({$fullName}): مبلغ خالص پرداختی باید بزرگتر از صفر باشد (" . number_format($net) . " ریال).";
            } else {
                $totalAmount += $net;
                $validCount++;
            }
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'valid_count' => $validCount,
            'total_amount' => $totalAmount,
        ];
    }

    /**
     * Compatibility alias for standard paya payment file generation.
     */
    public function generatePayaPaymentFile(HrmPayrollRun $run): array
    {
        return $this->generateBankExport($run, 'paya_standard');
    }

    /**
     * Generate standard banking bulk payment file for Iranian Banks and Central Bank ACH (پایا / ساتنا / بانک‌ها).
     *
     * @return array{
     *   total_records: int,
     *   total_amount: float,
     *   source_sheba: string,
     *   file_content: string,
     *   checksum: string,
     *   format: string
     * }
     */
    public function generateBankExport(HrmPayrollRun $run, string $format = 'paya_standard', ?string $companySheba = null): array
    {
        // 1. Strict validation
        $integrity = $this->validateBankExportIntegrity($run, $companySheba);
        if (! $integrity['is_valid']) {
            throw new InvalidArgumentException('خطای اعتبارسنجی پیش از صدور فایل بانکی: ' . implode(' | ', $integrity['errors']));
        }

        $sourceSheba = $companySheba ?: $this->resolveSourceSheba($run);
        $run->loadMissing(['items.employee.profile']);

        $lines = [];
        $totalAmount = 0.0;
        $dateStr = now()->format('Ymd');
        $batchRef = sprintf("%04d%02d-%03d", $run->year, $run->month, $run->id);

        // Header per format
        $header = match ($format) {
            'satna_rtgs' => "SATNA_HEADER:DATE={$dateStr}:BATCH={$batchRef}:SOURCE={$sourceSheba}:CURRENCY=IRR:COUNT={$integrity['valid_count']}:TOTAL=" . number_format($integrity['total_amount'], 0, '', ''),
            'melli_bmi' => "BMI_PAYROLL_HDR,{$dateStr},{$batchRef},{$integrity['valid_count']},{$sourceSheba}," . number_format($integrity['total_amount'], 0, '', ''),
            'mellat_batch' => "MEL_HEADER,{$sourceSheba},{$dateStr},{$integrity['valid_count']}," . number_format($integrity['total_amount'], 0, '', '') . ",SALARY",
            'tejarat_batch' => "TEJ*HDR*{$sourceSheba}*{$dateStr}*{$integrity['valid_count']}*" . number_format($integrity['total_amount'], 0, '', ''),
            'sepah_batch' => "SEPAH_HEADER|{$sourceSheba}|{$dateStr}|{$integrity['valid_count']}|" . number_format($integrity['total_amount'], 0, '', ''),
            'saderat_bsi' => "BSI_HDR,{$sourceSheba},{$dateStr},{$integrity['valid_count']}," . number_format($integrity['total_amount'], 0, '', ''),
            default => "HDR,{$sourceSheba}," . number_format($integrity['total_amount'], 0, '', '') . ",{$integrity['valid_count']},{$dateStr},{$batchRef}",
        };

        $lines[] = $header;

        $validCount = 0;
        foreach ($run->items as $item) {
            $emp = $item->employee;
            $profile = $emp?->profile;
            $net = (float) $item->net;
            if ($net <= 0) {
                continue;
            }

            $fullName = trim("{$emp->first_name} {$emp->last_name}");
            $nationalId = trim((string) ($profile?->national_id ?? $emp->national_id ?? ''));
            $sheba = trim((string) ($emp->sheba_number ?: ($profile?->sheba ?? $emp->bank_sheba ?? '')));

            $validCount++;
            $totalAmount += $net;
            $formattedNet = number_format($net, 0, '', '');
            $desc = "حقوق ماه {$run->month}/{$run->year} - پرسنلی {$emp->employee_code}";

            // Exact line formatting per Iranian Banking System:
            $line = match ($format) {
                'satna_rtgs' => sprintf("SATNA|%d|%s|%s|%s|%s|%s", $validCount, $sheba, $formattedNet, $fullName, $nationalId, $desc),
                'melli_bmi' => sprintf("%s,%s,%s,%s,%s", $sheba, $formattedNet, $fullName, $nationalId, $desc),
                'mellat_batch' => sprintf("%s,%s,%s,%s,%s", $sheba, $formattedNet, $fullName, $nationalId, $desc),
                'tejarat_batch' => sprintf("%s*%s*%s*%s*%s", $sheba, $formattedNet, $fullName, $nationalId, $batchRef),
                'sepah_batch' => sprintf("%s|%s|%s|%s|%s", $sheba, $formattedNet, $fullName, $nationalId, $desc),
                'saderat_bsi' => sprintf("%s,%s,%s,%s,واریز حقوق", $sheba, $formattedNet, $fullName, $nationalId),
                default => sprintf("%d,%s,%s,%s,%s,%s", $validCount, $sheba, $formattedNet, $fullName, $nationalId, $desc),
            };

            $lines[] = $line;
        }

        $fullContent = implode("\r\n", $lines);
        $checksum = hash('sha256', $fullContent);

        return [
            'total_records' => $validCount,
            'total_amount' => $totalAmount,
            'source_sheba' => $sourceSheba,
            'file_content' => $fullContent,
            'checksum' => $checksum,
            'format' => $format,
        ];
    }
}
