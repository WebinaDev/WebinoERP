<?php

namespace Modules\Hrm\Services;

use InvalidArgumentException;
use Modules\Hrm\Entities\HrmLegalExportRecord;
use Modules\Hrm\Entities\HrmPayrollRun;
use Modules\Hrm\Entities\HrmPayrollSetting;
use Modules\Hrm\Entities\HrmSubsidiaryCompany;

class HrmLegalDisketteService
{
    public function __construct(
        private readonly ?HrmInsuranceListService $insuranceService = null
    ) {}

    /**
     * Resolve enterprise workshop code from subsidiary company or organization settings.
     */
    private function resolveWorkshopCode(?string $provided = null, ?HrmPayrollRun $run = null): string
    {
        $code = trim((string) $provided);
        if ($code !== '' && strlen($code) === 10 && ctype_digit($code)) {
            return $code;
        }

        if ($run && $run->subsidiary_company_id && \Illuminate\Support\Facades\Schema::hasTable('hrm_subsidiary_companies')) {
            $company = HrmSubsidiaryCompany::query()->find($run->subsidiary_company_id);
            if ($company && ! empty($company->workshop_code) && strlen($company->workshop_code) === 10 && ctype_digit($company->workshop_code)) {
                return $company->workshop_code;
            }
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
    private function resolveTaxTfn(?string $provided = null, ?HrmPayrollRun $run = null): string
    {
        $tfn = trim((string) $provided);
        if ($tfn !== '') {
            return $tfn;
        }

        if ($run && $run->subsidiary_company_id && \Illuminate\Support\Facades\Schema::hasTable('hrm_subsidiary_companies')) {
            $company = HrmSubsidiaryCompany::query()->find($run->subsidiary_company_id);
            if ($company && ! empty($company->tax_tfn) && strlen($company->tax_tfn) >= 11) {
                return $company->tax_tfn;
            }
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
    private function resolvePostalCode(?HrmPayrollRun $run = null): string
    {
        if ($run && $run->subsidiary_company_id && \Illuminate\Support\Facades\Schema::hasTable('hrm_subsidiary_companies')) {
            $company = HrmSubsidiaryCompany::query()->find($run->subsidiary_company_id);
            if ($company && ! empty($company->postal_code) && strlen($company->postal_code) === 10 && ctype_digit($company->postal_code)) {
                return $company->postal_code;
            }
        }

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
        $code = $this->resolveWorkshopCode($workshopCode, $run);
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
            'total_wage' => $totals['insurable'],
            'total_employee_insurance' => $totals['employee_insurance'],
            'total_employer_insurance' => $totals['employer_insurance'],
            'dskkar00_content' => $dskkarText,
            'dskwor00_content' => $dskworText,
            'zip_base64' => base64_encode($zipBinary),
            'content_sample' => substr($dskkarText, 0, 500),
        ];
    }

    /**
     * Generate standard Iranian Tax Organization Diskette format (سامانه مالیات بر درآمد حقوق ماده ۸۴)
     *
     * @return array{
     *   record_count: int,
     *   total_taxable: float,
     *   total_tax: float,
     *   wh_content: string,
     *   wp_content: string,
     *   checksum: string
     * }
     */
    public function generateTaxDiskette(HrmPayrollRun $run, ?string $tfn = null): array
    {
        $taxTfn = $this->resolveTaxTfn($tfn, $run);
        $postalCode = $this->resolvePostalCode($run);

        $run->loadMissing(['items.employee']);

        $whLines = [];
        $wpLines = [];
        $totalTaxable = 0.0;
        $totalTax = 0.0;

        foreach ($run->items as $item) {
            $emp = $item->employee;
            if (! $emp) {
                continue;
            }

            $nationalId = trim((string) $emp->national_id);
            if (empty($nationalId) || strlen($nationalId) !== 10 || ! ctype_digit($nationalId)) {
                $name = "{$emp->first_name} {$emp->last_name}";
                throw new InvalidArgumentException("کد ملی ۱۰ رقمی برای کارمند «{$name}» نامعتبر است. صدور دیسکت مالیات متوقف شد.");
            }

            $taxable = (float) ($item->taxable_wage ?: $item->gross);
            $tax = (float) $item->tax;

            $totalTaxable += $taxable;
            $totalTax += $tax;

            // WH format (اطلاعات هویتی و پرسنلی مالیاتی)
            $whLines[] = implode('|', [
                $taxTfn,
                $nationalId,
                $emp->first_name,
                $emp->last_name,
                $emp->insurance_no ?: '',
                $postalCode,
                $emp->hire_date ? $emp->hire_date->format('Ymd') : '',
            ]);

            // WP format (اطلاعات مالی، حقوق ناخالص، معافیت‌ها و مالیات)
            $wpLines[] = implode('|', [
                $taxTfn,
                $nationalId,
                (int) $run->year,
                (int) $run->month,
                number_format($taxable, 0, '', ''),
                number_format($tax, 0, '', ''),
            ]);
        }

        $whContent = implode("\r\n", $whLines);
        $wpContent = implode("\r\n", $wpLines);
        $checksum = hash('sha256', $whContent . $wpContent);

        HrmLegalExportRecord::query()->create([
            'payroll_run_id' => $run->id,
            'export_type' => 'tax_diskette',
            'format' => 'wh_wp_pipe',
            'total_records' => count($whLines),
            'total_amount' => $totalTax,
            'metadata' => [
                'tax_tfn' => $taxTfn,
                'postal_code' => $postalCode,
                'year' => $run->year,
                'month' => $run->month,
                'total_taxable' => $totalTaxable,
            ],
        ]);

        return [
            'record_count' => count($whLines),
            'total_taxable' => $totalTaxable,
            'total_tax' => $totalTax,
            'wh_content' => $whContent,
            'wp_content' => $wpContent,
            'checksum' => $checksum,
        ];
    }
}
