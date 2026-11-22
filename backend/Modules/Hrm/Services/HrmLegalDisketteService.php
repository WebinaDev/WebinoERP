<?php

namespace Modules\Hrm\Services;

use Modules\Hrm\Entities\HrmLegalExportRecord;
use Modules\Hrm\Entities\HrmPayrollItem;
use Modules\Hrm\Entities\HrmPayrollRun;

class HrmLegalDisketteService
{
    public function __construct(
        private readonly ?HrmInsuranceListService $insuranceService = null
    ) {}

    /**
     * Generate standard Iranian SSO Insurance Diskette format:
     * - DSKKAR00.DBF & DSKWOR00.DBF (Windows-1256 dBase III standard)
     * - DSKKAR00.txt & DSKWOR00.txt
     * - ZIP package containing official DBFs
     * - Uses REAL worked days from HrmAttendanceRecord (not fixed 30)
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
    public function generateSsoDiskette(HrmPayrollRun $run, string $workshopCode = '0123456789'): array
    {
        $insuranceService = $this->insuranceService ?: app(HrmInsuranceListService::class);
        $header = $insuranceService->header((int) $run->year, (int) $run->month);
        if ($workshopCode !== '') {
            $header['workshop_code'] = $workshopCode;
        }

        $rows = $insuranceService->rows($run);

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
     * Format: WH (Header) / WP (Personnel) / WK (Tax calculation breakdown)
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
    public function generateTaxDiskette(HrmPayrollRun $run, string $taxTfn = '14000000000'): array
    {
        $items = HrmPayrollItem::query()->where('payroll_run_id', $run->id)->with('employee.profile')->get();
        $totalGross = 0.0;
        $totalTaxable = 0.0;
        $totalTax = 0.0;

        $whLines = [];
        $wpLines = [];

        // Header line (WH): TFN, Year, Month, TotalCount, TotalGross, TotalTaxable, TotalTax
        // Sample WH layout: WH,TFN,TaxYear,TaxMonth,Count,Gross,Taxable,Tax,PostalCode,ListType
        $whLines[] = sprintf("WH,%s,%04d,%02d,%d,%.0f,%.0f,%.0f,1999999999,MAIN", $taxTfn, (int) $run->year, (int) $run->month, count($items), 0, 0, 0);

        foreach ($items as $item) {
            $emp = $item->employee;
            $profile = $emp?->profile;

            $nationalCode = str_pad((string) ($profile?->national_id ?? $emp?->national_id ?? '0000000000'), 10, '0', STR_PAD_LEFT);
            $firstName = (string) ($emp?->first_name ?? 'کارمند');
            $lastName = (string) ($emp?->last_name ?? 'نمونه');
            $postalCode = '1999999999';

            $gross = (float) $item->gross;
            $taxable = (float) ($item->taxable_wage ?: $gross);
            $tax = (float) ($item->tax ?: 0);
            $exemption = max(0.0, $gross - $taxable);

            $totalGross += $gross;
            $totalTaxable += $taxable;
            $totalTax += $tax;

            // Worker line (WP): WP,NationalCode,FirstName,LastName,Country,TaxType,Gross,Exemption,Taxable,Tax,Deduction
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

        // Re-write WH with calculated totals
        $whLines[0] = sprintf("WH,%s,%04d,%02d,%d,%.0f,%.0f,%.0f,1999999999,MAIN", $taxTfn, (int) $run->year, (int) $run->month, count($items), $totalGross, $totalTaxable, $totalTax);

        $fullContent = implode("\r\n", $whLines) . "\r\n" . implode("\r\n", $wpLines);

        HrmLegalExportRecord::query()->create([
            'payroll_run_id' => $run->id,
            'export_type' => 'tax_diskette',
            'format' => 'txt_wh_wp',
            'total_records' => count($items),
            'total_amount' => $totalTax,
            'metadata' => [
                'tfn' => $taxTfn,
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
