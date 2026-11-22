<?php

namespace Modules\Hrm\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Modules\Hrm\Entities\HrmCareerPath;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmEmploymentDecree;
use Modules\Hrm\Entities\HrmKeyResult;
use Modules\Hrm\Entities\HrmObjective;
use Modules\Hrm\Entities\HrmPerformanceAgreement;
use Modules\Hrm\Entities\HrmPerformanceCycle;
use Modules\Hrm\Entities\HrmPerformanceReview;
use Modules\Hrm\Entities\HrmPromotionProposal;
use Modules\Hrm\Entities\HrmReview360;
use Modules\Hrm\Entities\HrmReview360Rating;
use Modules\Hrm\Entities\HrmTrainingCertificate;
use Modules\Hrm\Entities\HrmTrainingCourse;
use Modules\Hrm\Entities\HrmTrainingEnrollment;

class HrmPerformanceManagementService
{
    /**
     * 1. Register Training Course and Evaluate Training Effectiveness (Kirkpatrick model basis: Reaction/Learning/Behavior)
     *
     * @param array{
     *   title: string,
     *   description?: string,
     *   start_date?: string,
     *   end_date?: string,
     *   duration_hours?: int
     * } $data
     */
    public function registerTrainingCourse(array $data): HrmTrainingCourse
    {
        return HrmTrainingCourse::query()->create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'start_date' => $data['start_date'] ?? now()->toDateString(),
            'end_date' => $data['end_date'] ?? now()->addDays(14)->toDateString(),
            'status' => 'active',
        ]);
    }

    /**
     * Record training completion and evaluate training effectiveness score (0 - 100).
     *
     * @return array{
     *   course_id: int,
     *   employee_id: int,
     *   effectiveness_score: float,
     *   level: string,
     *   is_effective: bool
     * }
     */
    public function recordTrainingEffectiveness(
        int $courseId,
        int $employeeId,
        float $preTestScore,
        float $postTestScore,
        float $practicalEvaluationScore
    ): array {
        $enrollment = HrmTrainingEnrollment::query()->firstOrCreate(
            ['course_id' => $courseId, 'employee_id' => $employeeId],
            ['status' => 'completed']
        );

        $enrollment->update(['status' => 'completed']);

        // Learning gain + Practical on-the-job application
        $learningGain = max(0.0, $postTestScore - $preTestScore);
        $effectivenessScore = round(($postTestScore * 0.40) + ($practicalEvaluationScore * 0.40) + ($learningGain * 0.20), 2);
        $effectivenessScore = min(100.0, max(0.0, $effectivenessScore));

        $level = match (true) {
            $effectivenessScore >= 85.0 => 'excellent',
            $effectivenessScore >= 70.0 => 'satisfactory',
            $effectivenessScore >= 50.0 => 'needs_improvement',
            default => 'unsatisfactory',
        };

        return [
            'course_id' => $courseId,
            'employee_id' => $employeeId,
            'effectiveness_score' => $effectivenessScore,
            'level' => $level,
            'is_effective' => $effectivenessScore >= 70.0,
        ];
    }

    /**
     * 2. Performance Appraisal Cycle:
     * - Stage 1: Goal setting (هدف‌گذاری و تعیین KPI / OKR)
     * - Stage 2: Mid-term monitoring (ارزیابی میانی دوره)
     * - Stage 3: Final evaluation (ارزیابی نهایی و محاسبه نمره قطعی)
     */
    public function initiateCycleAppraisal(
        HrmEmployee $employee,
        string $yearPeriod,
        ?int $cycleId = null,
        array $objectives = [],
        ?string $managerNotes = null
    ): HrmPerformanceAgreement {
        return DB::transaction(function () use ($employee, $yearPeriod, $cycleId, $objectives, $managerNotes) {
            $agreement = HrmPerformanceAgreement::query()->create([
                'employee_id' => $employee->id,
                'cycle_id' => $cycleId,
                'year_period' => $yearPeriod,
                'stage' => 'planning', // مرحله اول: هدف‌گذاری
                'manager_notes' => $managerNotes ?: 'ثبت توافق‌نامه و هدف‌گذاری اهداف عملکردی دوره',
            ]);

            foreach ($objectives as $obj) {
                if (Schema::hasTable('hrm_objectives')) {
                    HrmObjective::query()->create([
                        'employee_id' => $employee->id,
                        'cycle_id' => $cycleId,
                        'title' => $obj['title'],
                        'weight' => $obj['weight'] ?? 1.0,
                        'period' => $yearPeriod,
                        'status' => 'active',
                    ]);
                }
            }

            return $agreement;
        });
    }

    /**
     * Mid-term review stage (ارزیابی میانی دوره)
     */
    public function recordMidTermEvaluation(
        HrmPerformanceAgreement $agreement,
        float $progressPercent,
        string $midTermNotes
    ): HrmPerformanceAgreement {
        $agreement->update([
            'stage' => 'monitoring',
            'manager_notes' => trim($agreement->manager_notes . " | ارزیابی میانی: پیشرفت {$progressPercent}% - {$midTermNotes}"),
        ]);

        return $agreement;
    }

    /**
     * Final evaluation stage (ارزیابی نهایی دوره و صدور نمره قطعی)
     */
    public function finalizeAgreement(HrmPerformanceAgreement $agreement, float $score, ?string $evalNotes = null): HrmPerformanceAgreement
    {
        if ($score < 0 || $score > 100) {
            throw new InvalidArgumentException('نمره ارزیابی عملکرد باید بین ۰ تا ۱۰۰ باشد.');
        }

        $baseSalary = (float) ($agreement->employee->base_salary ?? 0);
        $bonus = 0.0;
        if ($score >= 80.0) {
            $bonusRatio = ($score - 80.0) / 20.0; // 80 -> 0%, 100 -> 100% of base
            $bonus = round($baseSalary * $bonusRatio, 2);
        }

        $agreement->update([
            'stage' => 'finalized',
            'final_score' => $score,
            'performance_bonus_suggested' => $bonus,
            'manager_notes' => $evalNotes ? trim($agreement->manager_notes . " | ارزیابی نهایی: {$evalNotes}") : $agreement->manager_notes,
        ]);

        return $agreement;
    }

    /**
     * 3. 360-Degree Performance Review Skeleton (ارزیابی ۳۶۰ درجه):
     * Initiates 360 review with multiple raters (Self, Manager, Peer, Subordinate) and aggregates final score.
     */
    public function create360Review(HrmEmployee $employee, ?int $cycleId, array $raters): HrmReview360
    {
        return DB::transaction(function () use ($employee, $cycleId, $raters) {
            $review = HrmReview360::query()->create([
                'employee_id' => $employee->id,
                'cycle_id' => $cycleId,
                'due_date' => now()->addDays(20)->toDateString(),
                'status' => 'open',
            ]);

            foreach ($raters as $rater) {
                HrmReview360Rating::query()->create([
                    'review_id' => $review->id,
                    'rater_employee_id' => $rater['employee_id'] ?? null,
                    'rater_user_id' => $rater['user_id'] ?? null,
                    'relationship' => $rater['relationship'] ?? 'peer', // self, manager, peer, subordinate
                    'status' => 'pending',
                ]);
            }

            return $review;
        });
    }

    /**
     * Submit rating in 360 review and recalculate aggregated average score.
     */
    public function submit360Rating(HrmReview360Rating $rating, int $score, ?string $feedback = null): HrmReview360
    {
        $rating->update([
            'score' => $score,
            'feedback' => $feedback,
            'status' => 'completed',
            'submitted_at' => now(),
        ]);

        $review = $rating->review;
        $avg = HrmReview360Rating::query()
            ->where('review_id', $review->id)
            ->where('status', 'completed')
            ->avg('score');

        $pendingCount = HrmReview360Rating::query()
            ->where('review_id', $review->id)
            ->where('status', 'pending')
            ->count();

        $review->update([
            'average_score' => $avg !== null ? round((float) $avg, 2) : null,
            'status' => $pendingCount === 0 ? 'completed' : 'open',
        ]);

        return $review;
    }

    /**
     * 4. Link Performance Results to Career Progression & Promotion (اتصال ارزیابی به مسیر شغلی):
     * - Minimum performance threshold (e.g. >= 80)
     * - Generates or updates promotion proposal
     */
    public function linkPerformanceToCareerProgression(HrmEmployee $employee, float $finalScore): ?HrmPromotionProposal
    {
        if ($finalScore < 75.0) {
            return null; // Does not meet minimum performance threshold for career advancement
        }

        $careerProgressionService = app(HrmCareerProgressionService::class);
        return $careerProgressionService->evaluatePromotionEligibility($employee);
    }

    /**
     * Create formal performance agreement for an employee during the planning stage.
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
}
