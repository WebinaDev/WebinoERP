<?php

namespace Modules\Hrm\Services;

use Carbon\Carbon;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmPayrollSetting;

class HrmEydiSeveranceCalculatorService
{
    /**
     * Compute statutory Eydi & Bonus (محاسبه عیدی و پاداش سالانه مطابق قانون کار ایران)
     * Rule: 2 x Base Salary, with ceiling of 3 x Minimum Statutory Wage.
     * Pro-rated if working months in year < 12.
     *
     * @return array{
     *   worked_months: int,
     *   base_salary: float,
     *   calculated_eydi: float,
     *   statutory_ceiling: float,
     *   final_eydi: float,
     *   tax_exempt_portion: float,
     *   taxable_eydi: float,
     *   net_eydi: float
     * }
     */
    public function calculateYearlyEydi(HrmEmployee $employee, int $year = 1405): array
    {
        $base = (float) $employee->base_salary;
        $minWage = 166255500.0;

        $hireDate = $employee->hire_date ?? now()->startOfYear();
        $startOfYear = now()->startOfYear();

        $effectiveStart = $hireDate->greaterThan($startOfYear) ? $hireDate : $startOfYear;
        $workedDays = max(1, min(365, $effectiveStart->diffInDays(now()->endOfYear())));
        $workedMonths = (int) round($workedDays / 30);

        // 2x Base salary ceilinged at 3x Min statutory wage
        $statutoryCeiling = $minWage * 3.0;
        $rawEydi = ($base * 2.0) * ($workedDays / 365.0);
        $finalEydi = round(min($rawEydi, $statutoryCeiling * ($workedDays / 365.0)), 2);

        // Article 91 tax exemption (معافیت سالانه عیدی تا سقف ۱ ماه معافیت ماده ۸۴)
        $monthlyTaxExemption = 120000000.0;
        $taxExempt = min($finalEydi, $monthlyTaxExemption);
        $taxablePortion = max(0.0, $finalEydi - $taxExempt);
        $eydiTax = round($taxablePortion * 0.10, 2); // 10% flat tier
        $netEydi = round($finalEydi - $eydiTax, 2);

        return [
            'worked_months' => $workedMonths,
            'base_salary' => $base,
            'calculated_eydi' => round($rawEydi, 2),
            'statutory_ceiling' => $statutoryCeiling,
            'final_eydi' => $finalEydi,
            'tax_exempt_portion' => $taxExempt,
            'taxable_eydi' => $taxablePortion,
            'net_eydi' => $netEydi,
        ];
    }

    /**
     * Compute Severance reserve and Buyback (محاسبه حق سنوات خدمت و ذخیره سنوات پایان کار)
     * Rule: 1 month of last salary per year of completed service (ماده ۲۴ قانون کار).
     *
     * @return array{
     *   tenure_years: float,
     *   last_base_salary: float,
     *   total_accrued_severance: float,
     *   monthly_reserve_rate: float
     * }
     */
    public function calculateAccruedSeverance(HrmEmployee $employee): array
    {
        $base = (float) $employee->base_salary;
        $hireDate = $employee->hire_date ?? now();
        $tenureDays = max(1, $hireDate->diffInDays(now()));
        $tenureYears = round($tenureDays / 365.0, 2);

        $accruedSeverance = round($base * $tenureYears, 2);
        $monthlyReserve = round($base / 12.0, 2);

        return [
            'tenure_years' => $tenureYears,
            'last_base_salary' => $base,
            'total_accrued_severance' => $accruedSeverance,
            'monthly_reserve_rate' => $monthlyReserve,
        ];
    }
}
