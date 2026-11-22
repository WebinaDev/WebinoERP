<?php

namespace Modules\Hrm\Services;

use InvalidArgumentException;
use Modules\Hrm\Entities\HrmLegalExportRecord;
use Modules\Hrm\Entities\HrmPayrollRun;
use Modules\Hrm\Entities\HrmPayrollSetting;
use Modules\Hrm\Entities\HrmSubsidiaryCompany;
use Modules\Hrm\Support\IranianNationalId;

class HrmLegalDisketteService
{
    public function __construct(
        private readonly ?HrmInsuranceListService $insuranceService = null
    ) {}

    /**
     * Resolve enterprise workshop code from subsidiary company or organization settings.
     */
    public function resolveWorkshopCode(?string $provided = null, ?HrmPayrollRun $run = null): string
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
    public function resolveTaxTfn(?string $provided = null, ?HrmPayrollRun $run = null): string
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
    public function resolvePostalCode(?HrmPayrollRun $run = null): string
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
     * Validate pre-export integrity and return detailed discrepancy report:
     * - Itemized discrepancy report listing exact problematic employees and specific missing/invalid attributes
     * - Strict validation for Employee Full Name, 10-digit National ID, SSO Insurance Number, and Workshop Code.
     *
     * @return array{
     *   is_valid: bool,
     *   errors: list<string>,
     *   warnings: list<string>,
     *   discrepancies: list<array{
     *     row: int,
     *     employee_id: int|null,
     *     employee_name: string,
     *     employee_code: string,
     *     issue_type: string,
     *     description: string
     *   }>
     * }
     */
    public function validateExportIntegrity(HrmPayrollRun $run): array
    {
        $run->loadMissing(['items.employee.profile']);
        $errors = [];
        $warnings = [];
        $discrepancies = [];

        // 1. Verify Workshop Code configuration
        try {
            $this->resolveWorkshopCode(null, $run);
        } catch (\Throwable $e) {
            $errors[] = $e->getMessage();
            $discrepancies[] = [
                'row' => 0,
                'employee_id' => null,
                'employee_name' => 'تنظیمات کارگاه',
                'employee_code' => '-',
                'issue_type' => 'missing_workshop_code',
                'description' => $e->getMessage(),
            ];
        }

        // 2. Verify Runs & Items presence
        if ($run->items->isEmpty()) {
            $msg = 'دوره حقوق فاقد هرگونه رکورد یا فیش حقوقی است.';
            $errors[] = $msg;
            $discrepancies[] = [
                'row' => 0,
                'employee_id' => null,
                'employee_name' => '-',
                'employee_code' => '-',
                'issue_type' => 'empty_run',
                'description' => $msg,
            ];
            return [
                'is_valid' => false,
                'errors' => $errors,
                'warnings' => $warnings,
                'discrepancies' => $discrepancies,
            ];
        }

        foreach ($run->items as $idx => $item) {
            $line = $idx + 1;
            $emp = $item->employee;
            if (! $emp) {
                $msg = "ردیف {$line}: رکورد فیش به هیچ کارمندی متصل نیست.";
                $errors[] = $msg;
                $discrepancies[] = [
                    'row' => $line,
                    'employee_id' => null,
                    'employee_name' => 'نامشخص',
                    'employee_code' => '-',
                    'issue_type' => 'missing_employee_relation',
                    'description' => $msg,
                ];
                continue;
            }

            $name = trim("{$emp->first_name} {$emp->last_name}");
            $empCode = (string) ($emp->employee_code ?: $emp->id);

            if (empty($emp->first_name) || empty($emp->last_name)) {
                $msg = "ردیف {$line} (پرسنلی {$empCode}): نام و نام خانوادگی کارمند اجباری بوده و خالی است.";
                $errors[] = $msg;
                $discrepancies[] = [
                    'row' => $line,
                    'employee_id' => $emp->id,
                    'employee_name' => $name ?: 'خالی',
                    'employee_code' => $empCode,
                    'issue_type' => 'missing_name',
                    'description' => $msg,
                ];
            }

            $nid = trim((string) $emp->national_id);
            if (empty($nid) || ! IranianNationalId::isValid($nid)) {
                $msg = "ردیف {$line} ({$name} - پرسنلی {$empCode}): کد ملی ۱۰ رقمی کارمند نامعتبر است ({$nid}).";
                $errors[] = $msg;
                $discrepancies[] = [
                    'row' => $line,
                    'employee_id' => $emp->id,
                    'employee_name' => $name,
                    'employee_code' => $empCode,
                    'issue_type' => 'invalid_national_id',
                    'description' => $msg,
                ];
            }

            $profile = $emp->profile;
            $custom = is_array($profile?->custom_fields) ? $profile->custom_fields : [];
            $insuranceNo = trim((string) ($profile?->insurance_number ?: ($custom['insurance_no'] ?? $custom['ssn'] ?? $emp->insurance_no ?? '')));
            if (empty($insuranceNo)) {
                $msg = "ردیف {$line} ({$name} - پرسنلی {$empCode}): شماره بیمه تأمین اجتماعی کارمند اجباری بوده و ثبت نشده است.";
                $errors[] = $msg;
                $discrepancies[] = [
                    'row' => $line,
                    'employee_id' => $emp->id,
                    'employee_name' => $name,
                    'employee_code' => $empCode,
                    'issue_type' => 'missing_insurance_number',
                    'description' => $msg,
                ];
            }

            $sheba = trim((string) $emp->sheba_number);
            if (! empty($sheba) && (! str_starts_with($sheba, 'IR') || strlen($sheba) !== 26)) {
                $warnings[] = "ردیف {$line} ({$name} - پرسنلی {$empCode}): فرمت شبای بانکی غیراستاندارد است ({$sheba}).";
            }
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'discrepancies' => $discrepancies,
        ];
    }

    /**
     * Validate pre-export integrity specifically for Tax Diskette (WH/WP Article 84)
     *
     * @return array{
     *   is_valid: bool,
     *   errors: list<string>,
     *   warnings: list<string>,
     *   discrepancies: list<array{
     *     row: int,
     *     employee_id: int|null,
     *     employee_name: string,
     *     employee_code: string,
     *     issue_type: string,
     *     description: string
     *   }>
     * }
     */
    public function validateTaxExportIntegrity(HrmPayrollRun $run): array
    {
        $run->loadMissing(['items.employee.profile']);
        $errors = [];
        $warnings = [];
        $discrepancies = [];

        // 1. Verify Employer Tax TFN and Postal Code
        try {
            $this->resolveTaxTfn(null, $run);
        } catch (\Throwable $e) {
            $errors[] = $e->getMessage();
            $discrepancies[] = [
                'row' => 0,
                'employee_id' => null,
                'employee_name' => 'تنظیمات مالیاتی کارفرما',
                'employee_code' => '-',
                'issue_type' => 'missing_tax_tfn',
                'description' => $e->getMessage(),
            ];
        }

        try {
            $this->resolvePostalCode($run);
        } catch (\Throwable $e) {
            $errors[] = $e->getMessage();
            $discrepancies[] = [
                'row' => 0,
                'employee_id' => null,
                'employee_name' => 'تنظیمات کد پستی',
                'employee_code' => '-',
                'issue_type' => 'missing_postal_code',
                'description' => $e->getMessage(),
            ];
        }

        // 2. Verify Runs & Items presence
        if ($run->items->isEmpty()) {
            $msg = 'دوره حقوق فاقد هرگونه رکورد یا فیش حقوقی است.';
            $errors[] = $msg;
            $discrepancies[] = [
                'row' => 0,
                'employee_id' => null,
                'employee_name' => '-',
                'employee_code' => '-',
                'issue_type' => 'empty_run',
                'description' => $msg,
            ];
            return [
                'is_valid' => false,
                'errors' => $errors,
                'warnings' => $warnings,
                'discrepancies' => $discrepancies,
            ];
        }

        foreach ($run->items as $idx => $item) {
            $line = $idx + 1;
            $emp = $item->employee;
            if (! $emp) {
                $msg = "ردیف {$line}: رکورد فیش به هیچ کارمندی متصل نیست.";
                $errors[] = $msg;
                $discrepancies[] = [
                    'row' => $line,
                    'employee_id' => null,
                    'employee_name' => 'نامشخص',
                    'employee_code' => '-',
                    'issue_type' => 'missing_employee_relation',
                    'description' => $msg,
                ];
                continue;
            }

            $name = trim("{$emp->first_name} {$emp->last_name}");
            $empCode = (string) ($emp->employee_code ?: $emp->id);

            if (empty($emp->first_name) || empty($emp->last_name)) {
                $msg = "ردیف {$line} (پرسنلی {$empCode}): نام و نام خانوادگی کارمند در دیسکت مالیات اجباری است.";
                $errors[] = $msg;
                $discrepancies[] = [
                    'row' => $line,
                    'employee_id' => $emp->id,
                    'employee_name' => $name ?: 'خالی',
                    'employee_code' => $empCode,
                    'issue_type' => 'missing_name',
                    'description' => $msg,
                ];
            }

            $nid = trim((string) $emp->national_id);
            if (empty($nid) || ! IranianNationalId::isValid($nid)) {
                $msg = "ردیف {$line} ({$name} - پرسنلی {$empCode}): کد ملی ۱۰ رقمی برای دیسکت مالیات نامعتبر است ({$nid}).";
                $errors[] = $msg;
                $discrepancies[] = [
                    'row' => $line,
                    'employee_id' => $emp->id,
                    'employee_name' => $name,
                    'employee_code' => $empCode,
                    'issue_type' => 'invalid_national_id',
                    'description' => $msg,
                ];
            }
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'discrepancies' => $discrepancies,
        ];
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
        // 1. Mandatory pre-export validation
        $integrity = $this->validateExportIntegrity($run);
        if (! $integrity['is_valid']) {
            throw new InvalidArgumentException('خطای اعتبارسنجی پیش از تولید دیسکت بیمه: ' . implode(' | ', $integrity['errors']));
        }

        $code = $this->resolveWorkshopCode($workshopCode, $run);
        $insuranceService = $this->insuranceService ?: app(HrmInsuranceListService::class);
        $header = $insuranceService->header((int) $run->year, (int) $run->month);
        $header['workshop_code'] = $code;

        $rows = $insuranceService->rows($run);

        // Strict field-by-field verification
        foreach ($rows as $i => $r) {
            $line = $i + 1;
            if (empty($r['first_name']) || empty($r['last_name'])) {
                throw new InvalidArgumentException("نام یا نام خانوادگی کارمند در ردیف {$line} خالی است.");
            }
            if (empty($r['national_id'])) {
                throw new InvalidArgumentException("کد ملی کارمند در ردیف {$line} معتبر نیست.");
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
     * - Format conforms with Official INTA (سازمان امور مالیاتی کشور) WH & WP specifications
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
        $integrity = $this->validateTaxExportIntegrity($run);
        if (! $integrity['is_valid']) {
            throw new InvalidArgumentException('خطای اعتبارسنجی پیش از تولید دیسکت مالیات: ' . implode(' | ', $integrity['errors']));
        }

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
            if (empty($nationalId) || ! IranianNationalId::isValid($nationalId)) {
                $name = "{$emp->first_name} {$emp->last_name}";
                throw new InvalidArgumentException("کد ملی ۱۰ رقمی برای کارمند «{$name}» نامعتبر است. صدور دیسکت مالیات متوقف شد.");
            }

            $gross = (float) $item->gross;
            $taxable = (float) ($item->taxable_wage ?: $item->gross);
            $tax = (float) $item->tax;
            $exemptions = max(0.0, round($gross - $taxable, 2));

            $totalTaxable += $taxable;
            $totalTax += $tax;

            // WH format (مشخصات هویتی و شناسنامه‌ای پرسنل مالیاتی ماده ۸۴)
            // TFN | NationalID | FirstName | LastName | InsuranceNo | PostalCode | HireDate | CountryCode
            $whLines[] = implode('|', [
                $taxTfn,
                $nationalId,
                $emp->first_name,
                $emp->last_name,
                $emp->insurance_no ?: ($emp->profile?->insurance_number ?: ''),
                $postalCode,
                $emp->hire_date ? $emp->hire_date->format('Ymd') : now()->format('Ymd'),
                'IR', // National country code
            ]);

            // WP format (اطلاعات اقلام درآمدی، ناخالص، معافیت‌ها، مشمول و مالیات تکلیفی)
            // TFN | NationalID | Year | Month | Gross | Exemptions | Taxable | Tax
            $wpLines[] = implode('|', [
                $taxTfn,
                $nationalId,
                (int) $run->year,
                (int) $run->month,
                number_format($gross, 0, '', ''),
                number_format($exemptions, 0, '', ''),
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
