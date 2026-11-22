<?php

namespace Modules\Hrm\Services;

use InvalidArgumentException;
use Modules\Hrm\Entities\HrmPayrollRun;
use Modules\Hrm\Entities\HrmPayrollRunAuditLog;
use Modules\Hrm\Entities\HrmPayrollSetting;
use Modules\Hrm\Entities\HrmSubsidiaryCompany;
use Modules\Hrm\Entities\HrmWorkshop;

class HrmStandardBankFormatService
{
    /**
     * Resolve sending company SHEBA account from subsidiary company or organization settings.
     */
    private function resolveSourceSheba(?HrmPayrollRun $run = null): string
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
     * Generate standard banking bulk payment file (فرمت استاندارد پایا / ساتنا شبکه شتاب).
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
    public function generateBankExport(HrmPayrollRun $run, string $format = 'paya_standard'): array
    {
        $sourceSheba = $this->resolveSourceSheba($run);
        $run->loadMissing(['items.employee']);

        $lines = [];
        $totalAmount = 0.0;
        $validItems = 0;

        foreach ($run->items as $i => $item) {
            $emp = $item->employee;
            $net = (float) $item->net;
            if ($net <= 0) {
                continue;
            }

            $sheba = $emp ? trim((string) ($emp->sheba_number ?: '')) : '';
            if (empty($sheba) || ! $this->validateShebaMod97($sheba)) {
                $empName = $emp ? "{$emp->first_name} {$emp->last_name}" : 'کارمند ناشناس';
                throw new InvalidArgumentException("شماره شبای معتبر بانکی برای پرسنل «{$empName}» ثبت نشده است.");
            }

            $fullName = $emp ? "{$emp->first_name} {$emp->last_name}" : '';
            if (empty($fullName)) {
                throw new InvalidArgumentException("نام پرسنل در ردیف فیش {$item->id} خالی است.");
            }

            $nationalId = $emp ? trim((string) $emp->national_id) : '';
            if (empty($nationalId) || strlen($nationalId) !== 10 || ! ctype_digit($nationalId)) {
                throw new InvalidArgumentException("کد ملی ۱۰ رقمی برای پرسنل «{$fullName}» معتبر نیست.");
            }

            $transferDescription = "حقوق دوره {$run->month}/{$run->year} - کد کارگزینی: {$emp->employee_code}";

            $lines[] = implode(',', [
                $validItems + 1,
                $sheba,
                number_format($net, 0, '', ''),
                $fullName,
                $nationalId,
                $transferDescription,
            ]);

            $totalAmount += $net;
            $validItems++;
        }

        if ($validItems === 0) {
            throw new InvalidArgumentException('هیچ رکورد پرداختنی معتبری برای تولید فایل بانکی یافت نشد.');
        }

        $header = "HDR,{$sourceSheba}," . number_format($totalAmount, 0, '', '') . ",{$validItems}," . now()->format('Ymd');
        $fullContent = $header . "\n" . implode("\n", $lines);
        $checksum = hash('sha256', $fullContent);

        return [
            'total_records' => $validItems,
            'total_amount' => $totalAmount,
            'source_sheba' => $sourceSheba,
            'file_content' => $fullContent,
            'checksum' => $checksum,
            'format' => $format,
        ];
    }
}
