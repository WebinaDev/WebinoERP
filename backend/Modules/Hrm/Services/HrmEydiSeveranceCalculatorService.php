<?php

namespace Modules\Hrm\Services;

use Carbon\Carbon;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmEmployeeSettlement;
use Modules\Hrm\Entities\HrmLeaveBalance;

class HrmEydiSeveranceCalculatorService
{
    /**
     * Compute statutory Eydi & Bonus (محاسبه عیدی و پاداش سالانه مطابق قانون کار ایران)
     * Rule: 2 x Base Salary, with ceiling of 3 x Minimum Statutory Wage.
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
        $eydiTax = round($taxablePortion * 0.10, 2);
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
     * Compute Severance reserve and Buyback (ماده ۲۴ قانون کار)
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

    /**
     * Complete final end-of-employment settlement (تسویه حساب کامل خاتمه خدمت):
     * 1. Severance buyback (سنوات)
     * 2. Unused leave buyback (بازخرید مانده مرخصی استحقاقی)
     * 3. Pro-rated Eydi up to exit date (عیدی معوق تا تاریخ ترک کار)
     *
     * @return array<string, mixed>
     */
    public function executeEmployeeSettlement(HrmEmployee $employee, string $reason = 'resignation'): array
    {
        $severance = $this->calculateAccruedSeverance($employee);
        $eydi = $this->calculateYearlyEydi($employee);

        // Calculate unused annual leave days from balances
        $unusedDays = 0.0;
        if (\Illuminate\Support\Facades\Schema::hasTable('hrm_leave_balances')) {
            $balance = HrmLeaveBalance::query()->where('employee_id', $employee->id)->first();
            $unusedDays = (float) ($balance?->remaining_days ?? 12.0);
        } else {
            $unusedDays = 12.0;
        }

        $dailyRate = (float) ($employee->daily_rate ?: ($employee->base_salary / 30));
        $leaveBuyback = round($unusedDays * $dailyRate, 2);

        $totalPayable = round($severance['total_accrued_severance'] + $eydi['net_eydi'] + $leaveBuyback, 2);

        $settlement = HrmEmployeeSettlement::query()->create([
            'employee_id' => $employee->id,
            'settlement_date' => now()->toDateString(),
            'reason' => $reason,
            'tenure_years' => $severance['tenure_years'],
            'severance_amount' => $severance['total_accrued_severance'],
            'unused_leave_days' => $unusedDays,
            'leave_buyback_amount' => $leaveBuyback,
            'pro_rated_eydi_amount' => $eydi['net_eydi'],
            'final_net_payable' => $totalPayable,
            'status' => 'settled',
        ]);

        return [
            'settlement_id' => $settlement->id,
            'employee_id' => $employee->id,
            'severance_amount' => $severance['total_accrued_severance'],
            'unused_leave_days' => $unusedDays,
            'leave_buyback_amount' => $leaveBuyback,
            'pro_rated_eydi' => $eydi['net_eydi'],
            'total_settlement_net' => $totalPayable,
            'message' => 'تسویه حساب قانونی پایان خدمت با موفقیت محاسبه و ثبت شد.',
        ];
    }
}
