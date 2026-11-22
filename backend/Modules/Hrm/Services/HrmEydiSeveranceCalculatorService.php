<?php

namespace Modules\Hrm\Services;

use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmEmployeeSettlement;
use Modules\Hrm\Entities\HrmLeaveBalance;
use Modules\Hrm\Entities\HrmPayrollRun;
use Modules\Hrm\Entities\HrmPayrollSetting;
use Modules\Hrm\Entities\HrmRetireeRecord;
use Modules\Hrm\Entities\HrmSeveranceReserve;

class HrmEydiSeveranceCalculatorService
{
    /**
     * Compute statutory Iranian New Year Bonus (محاسبه قانونی عیدی و پاداش - مصوبه مجلس و ماده واحده)
     *
     * @return array{
     *   eligible_days: int,
     *   daily_wage: float,
     *   statutory_ceiling: float,
     *   final_eydi: float,
     *   tax_exempt_portion: float,
     *   taxable_eydi: float,
     *   net_eydi: float
     * }
     */
    public function calculateYearlyEydi(HrmEmployee $employee, ?int $year = null): array
    {
        $base = (float) $employee->base_salary;
        $daily = (float) ($employee->daily_rate ?: ($base / 30));

        $hireDate = $employee->hire_date ?? now()->startOfYear();
        $daysInYear = 365;
        $workedDays = min($daysInYear, max(1, (int) $hireDate->diffInDays(now())));

        $minimumDailyWage = 3165000;
        $statutoryCeiling = round($minimumDailyWage * 90, 2); // سقف ۹۰ روز حداقل دستمزد قانون کار

        // فرمول دو ماه حقوق پایه
        $rawEydi = round($daily * 60 * ($workedDays / $daysInYear), 2);
        $finalEydi = min($rawEydi, $statutoryCeiling);

        // معافیت مالیاتی یک‌دوازدهم ماده ۹۱ ق.م.م
        $taxExempt = 400000000 / 12;
        $taxablePortion = max(0.0, $finalEydi - $taxExempt);
        $tax = round($taxablePortion * 0.10, 2);
        $netEydi = round($finalEydi - $tax, 2);

        return [
            'eligible_days' => $workedDays,
            'daily_wage' => $daily,
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
     * Accrue and record monthly severance provisioning across active staff for a payroll run
     *
     * @return array{total_provision: float, employee_count: int}
     */
    public function accrueMonthlySeveranceProvision(HrmPayrollRun $run): array
    {
        $employees = HrmEmployee::query()->where('is_active', true)->get();
        $totalProvision = 0.0;

        foreach ($employees as $emp) {
            $base = (float) $emp->base_salary;
            $monthlyRate = round($base / 12.0, 2);
            $totalProvision += $monthlyRate;

            if (\Illuminate\Support\Facades\Schema::hasTable('hrm_severance_reserves')) {
                $prevRecord = HrmSeveranceReserve::query()
                    ->where('employee_id', $emp->id)
                    ->where(function ($q) use ($run) {
                        $q->where('year', '<', (int) $run->year)
                            ->orWhere(function ($q2) use ($run) {
                                $q2->where('year', (int) $run->year)->where('month', '<', (int) $run->month);
                            });
                    })
                    ->orderByDesc('year')
                    ->orderByDesc('month')
                    ->first();

                $lastCumulative = (float) ($prevRecord?->cumulative_balance ?? 0.0);

                HrmSeveranceReserve::query()->updateOrCreate(
                    [
                        'employee_id' => $emp->id,
                        'year' => (int) $run->year,
                        'month' => (int) $run->month,
                    ],
                    [
                        'payroll_run_id' => $run->id,
                        'monthly_provision' => $monthlyRate,
                        'cumulative_balance' => round($lastCumulative + $monthlyRate, 2),
                        'status' => 'accrued',
                    ]
                );
            }
        }

        return [
            'total_provision' => $totalProvision,
            'employee_count' => count($employees),
        ];
    }

    /**
     * Complete final end-of-employment settlement (تسویه حساب کامل خاتمه خدمت):
     * Directly consumes cumulative balance from `hrm_severance_reserves` when available.
     *
     * @return array<string, mixed>
     */
    public function executeEmployeeSettlement(HrmEmployee $employee, string $reason = 'resignation'): array
    {
        $severanceCalc = $this->calculateAccruedSeverance($employee);
        $eydi = $this->calculateYearlyEydi($employee);

        // Fetch cumulative balance from systematic monthly reserves if recorded
        $reserveBalance = 0.0;
        if (\Illuminate\Support\Facades\Schema::hasTable('hrm_severance_reserves')) {
            $lastReserve = HrmSeveranceReserve::query()
                ->where('employee_id', $employee->id)
                ->orderByDesc('id')
                ->first();
            if ($lastReserve) {
                $reserveBalance = (float) $lastReserve->cumulative_balance;
            }
        }

        $finalSeveranceAmount = $reserveBalance > 0
            ? max($reserveBalance, (float) $severanceCalc['total_accrued_severance'])
            : (float) $severanceCalc['total_accrued_severance'];

        $unusedDays = 0.0;
        if (\Illuminate\Support\Facades\Schema::hasTable('hrm_leave_balances')) {
            $balance = HrmLeaveBalance::query()->where('employee_id', $employee->id)->first();
            $unusedDays = (float) ($balance?->remaining_days ?? 12.0);
        } else {
            $unusedDays = 12.0;
        }

        $dailyRate = (float) ($employee->daily_rate ?: ($employee->base_salary / 30));
        $leaveBuyback = round($unusedDays * $dailyRate, 2);

        $totalPayable = round($finalSeveranceAmount + $eydi['net_eydi'] + $leaveBuyback, 2);

        $settlement = HrmEmployeeSettlement::query()->create([
            'employee_id' => $employee->id,
            'settlement_date' => now()->toDateString(),
            'reason' => $reason,
            'tenure_years' => $severanceCalc['tenure_years'],
            'severance_amount' => $finalSeveranceAmount,
            'unused_leave_days' => $unusedDays,
            'leave_buyback_amount' => $leaveBuyback,
            'pro_rated_eydi_amount' => $eydi['net_eydi'],
            'final_net_payable' => $totalPayable,
            'status' => 'settled',
        ]);

        return [
            'settlement_id' => $settlement->id,
            'employee_id' => $employee->id,
            'severance_amount' => $finalSeveranceAmount,
            'unused_leave_days' => $unusedDays,
            'leave_buyback_amount' => $leaveBuyback,
            'pro_rated_eydi' => $eydi['net_eydi'],
            'total_settlement_net' => $totalPayable,
            'message' => 'تسویه حساب قانونی پایان خدمت با موفقیت بر اساس مانده ذخیره سنوات محاسبه و ثبت شد.',
        ];
    }
}
