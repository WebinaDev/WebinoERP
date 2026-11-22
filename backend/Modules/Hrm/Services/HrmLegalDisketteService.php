<?php

namespace Modules\Hrm\Services;

use InvalidArgumentException;
use Modules\Hrm\Entities\HrmLegalExportRecord;
use Modules\Hrm\Entities\HrmPayrollItem;
use Modules\Hrm\Entities\HrmPayrollRun;
use Modules\Hrm\Entities\HrmPayrollSetting;

class HrmLegalDisketteService
{
    public function __construct(
        private readonly ?HrmInsuranceListService $insuranceService = null
    ) {}

    /**
     * Resolve official enterprise workshop code from organization payroll settings.
     * Throws InvalidArgumentException if unconfigured, eliminating fake defaults.
     */
    private function resolveWorkshopCode(?string $provided = null): string
    {
        $code = trim((string) $provided);
        if ($code !== '') {
            return $code;
        }

        $setting = HrmPayrollSetting::query()->where('key', 'workshop_code')->first();
        $code = trim((string) ($setting?->value ?? ''));

        if ($code === '' || strlen($code) !== 10 || ! ctype_digit($code)) {
            throw new InvalidArgumentException('کد کارگاه معتبر ۱۰ رقمی تأمین اجتماعی در تنظیمات سازمان تعریف نشده است.');
        }

        return $code;
    }

    /**
     * Resolve enterprise Tax Identification Number (کد اقتصادی یا شناسه ملی کارفرما).
     */
    private function resolveTaxTfn(?string $provided = null): string
    {
        $tfn = trim((string) $provided);
        if ($tfn !== '') {
            return $tfn;
        }

        $setting = HrmPayrollSetting::query()->where('key', 'tax_tfn')->first();
        $tfn = trim((string) ($setting?->value ?? ''));

        if ($tfn === '' || strlen($tfn) < 11) {
            throw new InvalidArgumentException('شناسه ملی / کد اقتصادی کارفرما جهت صدور دیسکت مالیات حقوق در تنظیمات سازمان ثبت نشده است.');
        }

        return $tfn;
    }

    /**
     * Resolve enterprise postal code.
     */
    private function resolvePostalCode(): string
    {
        $setting = HrmPayrollSetting::query()->where('key', 'postal_code')->first();
        $postal = trim((string) ($setting?->value ?? ''));

        if ($postal === '' || strlen($postal) !== 10 || ! ctype_digit($postal)) {
            throw new InvalidArgumentException('کد پستی ۱۰ رقمی قانونی کارگاه در تنظیمات حقوق سازمان تعریف نشده است.');
        }

        return $postal;
    }

    /**
     * Generate standard Iranian SSO Insurance Diskette format
     *
     * @return array{
     *   record_count: int,
     *   total_wage: float,
     *   total_employee_insurance: float,
     *   total_employer_insurance: float,
     *   dskkar00_content: string,
     *   dskwor00_content: string,
     *   zip_base64: string,
     *   content_sample: string
     * }
     */
    public function generateSsoDiskette(HrmPayrollRun $run, ?string $workshopCode = null): array
    {
        $code = $this->resolveWorkshopCode($workshopCode);
        $insuranceService = $this->insuranceService ?: app(HrmInsuranceListService::class);
        $header = $insuranceService->header((int) $run->year, (int) $run->month);
        $header['workshop_code'] = $code;

        $rows = $insuranceService->rows($run);

        // Strict pre-export validation: ensure no missing critical employee data
        foreach ($rows as $i => $r) {
            $line = $i + 1;
            if (empty($r['first_name']) || empty($r['last_name'])) {
                throw new InvalidArgumentException("نام یا نام خانوادگی کارمند در ردیف {$line} خالی است. صدور دیسکت قانونی متوقف شد.");
            }
            if (empty($r['national_id'])) {
                throw new InvalidArgumentException("کد ملی کارمند در ردیف {$line} ({$r['first_name']} {$r['last_name']}) معتبر نیست.");
            }
            if (empty($r['insurance_no'])) {
                throw new InvalidArgumentException("شماره بیمه تأمین اجتماعی کارمند در ردیف {$line} ثبت نشده است.");
            }
        }

        $dskkarText = $insuranceService->dskkarText($header, $rows);
        $dskworText = $insuranceService->dskworText($header, $rows);
        $zipBinary = $insuranceService->zip($header, $rows);

        $totals = $insuranceService->totals($rows);

        HrmLegalExportRecord::query()->create([
            'payroll_run_id' => $run->id,
            'export_type' => 'sso_diskette',
            'format' => 'zip_dbf_txt',
            'total_records' => count($rows),
            'total_amount' => $totals['insurable'],
            'metadata' => [
                'workshop_code' => $header['workshop_code'],
                'year' => $run->year,
                'month' => $run->month,
                'total_worker_share' => $totals['employee_insurance'],
                'total_employer_share' => $totals['employer_insurance'],
            ],
        ]);

        return [
            'record_count' => count($rows),
            'total_wage' => (float) $totals['insurable'],
            'total_employee_insurance' => (float) $totals['employee_insurance'],
            'total_employer_insurance' => (float) $totals['employer_insurance'],
            'dskkar00_content' => $dskkarText,
            'dskwor00_content' => $dskworText,
            'zip_base64' => base64_encode($zipBinary),
            'content_sample' => mb_substr($dskkarText, 0, 300) . "\r\n" . mb_substr($dskworText, 0, 500),
        ];
    }

    /**
     * Generate standard Iranian Salary Tax Diskette (سامانه مالیات بر حقوق سازمان امور مالیاتی کشور - ماده ۸۴)
     * Format: WH (Header) / WP (Personnel)
     *
     * @return array{
     *   record_count: int,
     *   total_gross: float,
     *   total_taxable: float,
     *   total_tax: float,
     *   wh_content: string,
     *   wp_content: string,
     *   tax_diskette_content: string
     * }
     */
    public function generateTaxDiskette(HrmPayrollRun $run, ?string $taxTfn = null): array
    {
        $tfn = $this->resolveTaxTfn($taxTfn);
        $postalCode = $this->resolvePostalCode();

        $items = HrmPayrollItem::query()->where('payroll_run_id', $run->id)->with('employee.profile')->get();
        if ($items->isEmpty()) {
            throw new InvalidArgumentException('هیچ رکوردی در این دوره حقوق برای صدور دیسکت مالیات وجود ندارد.');
        }

        $totalGross = 0.0;
        $totalTaxable = 0.0;
        $totalTax = 0.0;

        $whLines = [];
        $wpLines = [];

        $whLines[] = sprintf("WH,%s,%04d,%02d,%d,0,0,0,%s,MAIN", $tfn, (int) $run->year, (int) $run->month, count($items), $postalCode);

        foreach ($items as $i => $item) {
            $emp = $item->employee;
            $profile = $emp?->profile;

            $firstName = trim((string) $emp?->first_name);
            $lastName = trim((string) $emp?->last_name);
            $nationalCode = trim((string) ($profile?->national_id ?? $emp?->national_id ?? ''));

            if ($firstName === '' || $lastName === '') {
                throw new InvalidArgumentException("نام و نام خانوادگی کارمند شناسه #{$emp?->id} ثبت نشده است. درج مشخصات پیش‌فرض در دیسکت مالیات مجاز نیست.");
            }

            if (! \Modules\Hrm\Support\IranianNationalId::isValid($nationalCode)) {
                throw new InvalidArgumentException("کد ملی کارمند ({$firstName} {$lastName}) معتبر نیست. صدور دیسکت مالیات رد شد.");
            }

            $gross = (float) $item->gross;
            $taxable = (float) ($item->taxable_wage ?: $gross);
            $tax = (float) ($item->tax ?: 0);
            $exemption = max(0.0, $gross - $taxable);

            $totalGross += $gross;
            $totalTaxable += $taxable;
            $totalTax += $tax;

            $wpLines[] = sprintf(
                "WP,%s,%s,%s,IRN,1,%.0f,%.0f,%.0f,%.0f,0",
                $nationalCode,
                $firstName,
                $lastName,
                $gross,
                $exemption,
                $taxable,
                $tax
            );
        }

        $whLines[0] = sprintf("WH,%s,%04d,%02d,%d,%.0f,%.0f,%.0f,%s,MAIN", $tfn, (int) $run->year, (int) $run->month, count($items), $totalGross, $totalTaxable, $totalTax, $postalCode);

        $fullContent = implode("\r\n", $whLines) . "\r\n" . implode("\r\n", $wpLines);

        HrmLegalExportRecord::query()->create([
            'payroll_run_id' => $run->id,
            'export_type' => 'tax_diskette',
            'format' => 'txt_wh_wp',
            'total_records' => count($items),
            'total_amount' => $totalTax,
            'metadata' => [
                'tfn' => $tfn,
                'postal_code' => $postalCode,
                'year' => $run->year,
                'month' => $run->month,
                'total_gross' => $totalGross,
                'total_tax' => $totalTax,
            ],
        ]);

        return [
            'record_count' => count($items),
            'total_gross' => $totalGross,
            'total_taxable' => $totalTaxable,
            'total_tax' => $totalTax,
            'wh_content' => implode("\r\n", $whLines),
            'wp_content' => implode("\r\n", $wpLines),
            'tax_diskette_content' => $fullContent,
        ];
    }
}
