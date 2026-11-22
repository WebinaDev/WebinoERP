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

        // Find existing payroll items that were processed under the previous decree/rates
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
            ->where('status', 'approved')
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

            // Temporarily evaluate new compensation with the updated decree parameters
            $originalGross = (float) $existingItem->gross;
            $originalNet = (float) $existingItem->net;

            $newBase = (float) ($decree->base_salary ?: $employee->base_salary);
            $newDaily = (float) ($decree->daily_wage ?: $employee->daily_rate);

            // Re-simulate calculation with the new base rate
            $tempEmployee = clone $employee;
            $tempEmployee->base_salary = $newBase;
            if ($newDaily > 0) {
                $tempEmployee->daily_rate = $newDaily;
            }

            $simulated = $this->calculator->forEmployee($tempEmployee, (int) $run->year, (int) $run->month, $run->id);

            $adjustedGross = (float) $simulated['gross'];
            $diffGross = round($adjustedGross - $originalGross, 2);

            if ($diffGross <= 0) {
                continue;
            }

            $diffInsurance = round($diffGross * 0.07, 2); // 7% worker SSO
            $diffTax = round($diffGross * 0.10, 2);       // standard 10% progressive tier
            $diffNet = round($diffGross - $diffInsurance - $diffTax, 2);

            $adjustment = HrmPayrollRetroAdjustment::query()->create([
                'employee_id' => $employee->id,
                'decree_id' => $decree->id,
                'payroll_run_id' => $run->id,
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
}
