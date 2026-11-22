<?php

namespace Modules\Hrm\Services;

use Modules\Hrm\Entities\HrmLegalExportRecord;
use Modules\Hrm\Entities\HrmPayrollItem;
use Modules\Hrm\Entities\HrmPayrollRun;

class HrmLegalDisketteService
{
    /**
     * Generate standard Iranian SSO Insurance Diskette format:
     * - DSKKAR00.txt: Workshop and summary header
     * - DSKWOR00.txt: Individual insured personnel wage and contribution records
     *
     * @return array{
     *   record_count: int,
     *   total_wage: float,
     *   total_employee_insurance: float,
     *   total_employer_insurance: float,
     *   dskkar00_content: string,
     *   dskwor00_content: string,
     *   content_sample: string
     * }
     */
    public function generateSsoDiskette(HrmPayrollRun $run, string $workshopCode = '0123456789'): array
    {
        $items = HrmPayrollItem::query()->where('payroll_run_id', $run->id)->with('employee')->get();
        $totalWage = 0.0;
        $totalEmpIns = 0.0;
        $totalEmplrIns = 0.0;

        $worLines = [];

        foreach ($items as $index => $item) {
            $emp = $item->employee;
            $nationalCode = str_pad((string) ($emp?->national_id ?? '0000000000'), 10, '0', STR_PAD_LEFT);
            $insuranceNo = str_pad((string) ($emp?->insurance_no ?? '12345678'), 10, '0', STR_PAD_LEFT);
            $firstName = mb_substr((string) ($emp?->first_name ?? 'کارمند'), 0, 20);
            $lastName = mb_substr((string) ($emp?->last_name ?? 'نمونه'), 0, 30);
            $fatherName = mb_substr((string) ($emp?->father_name ?? 'پدر'), 0, 20);

            $insurable = (float) ($item->insurable_wage ?: $item->gross);
            $empIns = round($insurable * 0.07, 0); // 7% worker
            $emplrIns = round($insurable * 0.23, 0); // 20% employer + 3% unemployment

            $totalWage += $insurable;
            $totalEmpIns += $empIns;
            $totalEmplrIns += $emplrIns;

            // Iranian SSO DSKWOR00 fixed-width compliant line
            $worLines[] = sprintf(
                "%-10s%-10s%-10s%-20s%-30s%-20s%04d%02d%02d%012.0f%012.0f%012.0f",
                $workshopCode,
                $insuranceNo,
                $nationalCode,
                $firstName,
                $lastName,
                $fatherName,
                (int) $run->year,
                (int) $run->month,
                30, // standard working days in month
                $insurable,
                $empIns,
                $emplrIns
            );
        }

        // Workshop header DSKKAR00
        $karLine = sprintf(
            "%-10s%-30s%04d%02d%06d%015.0f%015.0f%015.0f",
            $workshopCode,
            "کارگاه شرکت وبینا",
            (int) $run->year,
            (int) $run->month,
            count($items),
            $totalWage,
            $totalEmpIns,
            $totalEmplrIns
        );

        $dskkarContent = $karLine;
        $dskworContent = implode("\r\n", $worLines);

        HrmLegalExportRecord::query()->create([
            'payroll_run_id' => $run->id,
            'export_type' => 'sso_diskette',
            'format' => 'txt',
            'total_records' => count($items),
            'total_amount' => $totalWage,
            'metadata' => [
                'workshop_code' => $workshopCode,
                'year' => $run->year,
                'month' => $run->month,
                'total_worker_share' => $totalEmpIns,
                'total_employer_share' => $totalEmplrIns,
            ],
        ]);

        return [
            'record_count' => count($items),
            'total_wage' => $totalWage,
            'total_employee_insurance' => $totalEmpIns,
            'total_employer_insurance' => $totalEmplrIns,
            'dskkar00_content' => $dskkarContent,
            'dskwor00_content' => $dskworContent,
            'content_sample' => $karLine . "\r\n" . implode("\r\n", array_slice($worLines, 0, 5)),
        ];
    }

    /**
     * Generate standard Iranian Salary Tax Diskette (سامانه مالیات بر حقوق سازمان امور مالیاتی کشور - ماده ۸۴)
     * Format: WP/WH structure (Worker Payroll / Worker Header)
     *
     * @return array{
     *   record_count: int,
     *   total_gross: float,
     *   total_taxable: float,
     *   total_tax: float,
     *   tax_diskette_content: string
     * }
     */
    public function generateTaxDiskette(HrmPayrollRun $run, string $taxTfn = '14000000000'): array
    {
        $items = HrmPayrollItem::query()->where('payroll_run_id', $run->id)->with('employee')->get();
        $totalGross = 0.0;
        $totalTaxable = 0.0;
        $totalTax = 0.0;

        $lines = [];

        // Header record
        $lines[] = sprintf("TH|%s|%04d|%02d|%d", $taxTfn, (int) $run->year, (int) $run->month, count($items));

        foreach ($items as $item) {
            $emp = $item->employee;
            $nationalCode = str_pad((string) ($emp?->national_id ?? '0000000000'), 10, '0', STR_PAD_LEFT);
            $gross = (float) $item->gross;
            $taxable = (float) ($item->taxable_wage ?: $gross);
            $tax = (float) ($item->tax ?: 0);

            $totalGross += $gross;
            $totalTaxable += $taxable;
            $totalTax += $tax;

            // Worker tax detail line: TP|NationalCode|Gross|Taxable|Tax
            $lines[] = sprintf(
                "TP|%s|%.0f|%.0f|%.0f|%s",
                $nationalCode,
                $gross,
                $taxable,
                $tax,
                $tax > 0 ? 'Y' : 'N'
            );
        }

        $content = implode("\r\n", $lines);

        HrmLegalExportRecord::query()->create([
            'payroll_run_id' => $run->id,
            'export_type' => 'tax_diskette',
            'format' => 'txt',
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
            'tax_diskette_content' => $content,
        ];
    }
}
