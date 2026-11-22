<?php

namespace Modules\Hrm\Services;

use InvalidArgumentException;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmPerformanceAgreement;
use Modules\Hrm\Entities\HrmPerformanceCycle;
use Modules\Hrm\Entities\HrmPerformanceReview;
use Modules\Hrm\Support\HrmNotifier;

class HrmPerformanceManagementService
{
    /**
     * Create formal performance agreement for an employee during the planning stage:
     * - Specifies cycle, period, goals and expected target metrics.
     */
    public function createAgreement(
        HrmEmployee $employee,
        string $yearPeriod,
        ?int $cycleId = null,
        ?string $managerNotes = null
    ): HrmPerformanceAgreement {
        return HrmPerformanceAgreement::query()->create([
            'employee_id' => $employee->id,
            'cycle_id' => $cycleId,
            'year_period' => $yearPeriod,
            'stage' => 'planning',
            'manager_notes' => $managerNotes,
        ]);
    }

    /**
     * Advance agreement stage: planning -> monitoring -> evaluation -> finalized
     */
    public function transitionStage(HrmPerformanceAgreement $agreement, string $targetStage): HrmPerformanceAgreement
    {
        $validStages = ['planning', 'monitoring', 'evaluation', 'finalized'];
        if (! in_array($targetStage, $validStages, true)) {
            throw new InvalidArgumentException("مرحله ارزیابی عملکرد «{$targetStage}» نامعتبر است.");
        }

        $agreement->update(['stage' => $targetStage]);
        return $agreement;
    }

    /**
     * Finalize performance agreement score and compute suggested performance bonus.
     */
    public function finalizeAgreement(HrmPerformanceAgreement $agreement, float $score, ?string $evalNotes = null): HrmPerformanceAgreement
    {
        if ($score < 0 || $score > 100) {
            throw new InvalidArgumentException('نمره ارزیابی عملکرد باید بین ۰ تا ۱۰۰ باشد.');
        }

        $baseSalary = (float) ($agreement->employee->base_salary ?? 0);
        // Suggested bonus: up to 100% of monthly base proportional to score >= 80%
        $bonus = 0.0;
        if ($score >= 80.0) {
            $bonusRatio = ($score - 80.0) / 20.0; // 80 -> 0%, 100 -> 100% of base
            $bonus = round($baseSalary * $bonusRatio, 2);
        }

        $agreement->update([
            'stage' => 'finalized',
            'final_score' => $score,
            'performance_bonus_suggested' => $bonus,
            'manager_notes' => $evalNotes ?: $agreement->manager_notes,
        ]);

        return $agreement;
    }
}
