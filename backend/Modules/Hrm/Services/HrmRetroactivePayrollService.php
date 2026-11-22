<?php

namespace Modules\Hrm\Services;

use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmEmploymentDecree;
use Modules\Hrm\Entities\HrmPayrollItem;
use Modules\Hrm\Entities\HrmPayrollRetroAdjustment;
use Modules\Hrm\Entities\HrmPayrollRun;

class HrmRetroactivePayrollService
{
    public function __construct(
        private readonly IranianPayrollCalculator $calculator
    ) {}

    /**
     * Calculate retroactive pay differentials when a new decree is issued effective in the past.
     * Uses real progressive tax brackets (پلکان قانونی ماده ۸۴) rather than a flat 10%.
     *
     * @return array<int, HrmPayrollRetroAdjustment>
     */
    public function calculateRetroDifferentials(HrmEmploymentDecree $decree): array
    {
        $employee = $decree->employee;
        if (! $employee || ! $decree->effective_from) {
            return [];
        }

        $effectiveDate = $decree->effective_from;
        $now = now();
        $decreeIssueDate = $decree->created_at ?: $now;

        // If effective date is in the current month or future, no retro adjustment needed
        if ($effectiveDate->format('Y-m') >= $decreeIssueDate->format('Y-m')) {
            return [];
        }

        $effectiveYear = (int) $effectiveDate->format('Y');
        $effectiveMonth = (int) $effectiveDate->format('n');
        $issueYear = (int) $decreeIssueDate->format('Y');
        $issueMonth = (int) $decreeIssueDate->format('n');

        // Find existing payroll runs processed under previous rates
        $historicalRuns = HrmPayrollRun::query()
            ->where(function ($q) use ($effectiveYear, $effectiveMonth, $issueYear, $issueMonth) {
                $q->where(function ($q2) use ($effectiveYear, $effectiveMonth) {
                    $q2->where('year', '>', $effectiveYear)
                        ->orWhere(function ($q3) use ($effectiveYear, $effectiveMonth) {
                            $q3->where('year', $effectiveYear)->where('month', '>=', $effectiveMonth);
                        });
                })->where(function ($q4) use ($issueYear, $issueMonth) {
                    $q4->where('year', '<', $issueYear)
                        ->orWhere(function ($q5) use ($issueYear, $issueMonth) {
                            $q5->where('year', $issueYear)->where('month', '<', $issueMonth);
                        });
                });
            })
            ->whereIn('status', ['approved', 'paid'])
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        $adjustments = [];

        foreach ($historicalRuns as $run) {
            $existingItem = HrmPayrollItem::query()
                ->where('payroll_run_id', $run->id)
                ->where('employee_id', $employee->id)
                ->first();

            if (! $existingItem) {
                continue;
            }

            $originalGross = (float) $existingItem->gross;
            $originalNet = (float) $existingItem->net;
            $originalTax = (float) $existingItem->tax;
            $originalIns = (float) $existingItem->employee_insurance;

            $newBase = (float) ($decree->base_salary ?: $employee->base_salary);
            $newDaily = (float) ($decree->daily_wage ?: $employee->daily_rate);

            // Re-simulate calculation with the new base rate and progressive tax brackets
            $tempEmployee = clone $employee;
            $tempEmployee->base_salary = $newBase;
            if ($newDaily > 0) {
                $tempEmployee->daily_rate = $newDaily;
            }

            $simulated = $this->calculator->forEmployee($tempEmployee, (int) $run->year, (int) $run->month, $run->id);

            $adjustedGross = (float) $simulated['gross'];
            $adjustedTax = (float) ($simulated['breakdown']['tax'] ?? 0);
            $adjustedIns = (float) ($simulated['breakdown']['employee_insurance'] ?? 0);

            $diffGross = round($adjustedGross - $originalGross, 2);
            if ($diffGross <= 0) {
                continue;
            }

            // Exact progressive differential tax & insurance
            $diffInsurance = round(max(0.0, $adjustedIns - $originalIns), 2);
            $diffTax = round(max(0.0, $adjustedTax - $originalTax), 2);
            $diffNet = round($diffGross - $diffInsurance - $diffTax, 2);

            $adjustment = HrmPayrollRetroAdjustment::query()->create([
                'employee_id' => $employee->id,
                'decree_id' => $decree->id,
                'payroll_run_id' => null, // pending allocation to next active run
                'target_year' => $run->year,
                'target_month' => $run->month,
                'original_gross' => $originalGross,
                'adjusted_gross' => $adjustedGross,
                'differential_amount' => $diffGross,
                'differential_insurance' => $diffInsurance,
                'differential_tax' => $diffTax,
                'differential_net' => $diffNet,
                'status' => 'pending',
                'reason' => "مابه‌التفاوت عطف‌به‌ماسبق حکم کارگزینی {$decree->decree_no} بابت ماه {$run->month}/{$run->year}",
                'metadata' => [
                    'decree_no' => $decree->decree_no,
                    'previous_base' => $employee->base_salary,
                    'new_base' => $newBase,
                    'is_prior_fiscal_year' => (int) $run->year < (int) now()->format('Y'),
                ],
            ]);

            $adjustments[] = $adjustment;
        }

        if (! empty($adjustments)) {
            $decree->update([
                'is_retroactive' => true,
                'retro_applied_at' => now(),
            ]);
        }

        return $adjustments;
    }

    /**
     * Workflow: approve pending retro adjustment so it can be automatically pulled into next payroll run.
     */
    public function approveAdjustment(HrmPayrollRetroAdjustment $adjustment): void
    {
        $adjustment->update(['status' => 'applied']);
    }
}
