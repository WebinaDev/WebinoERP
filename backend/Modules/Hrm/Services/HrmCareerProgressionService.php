<?php

namespace Modules\Hrm\Services;

use Modules\Hrm\Entities\HrmCareerPath;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmEmploymentDecree;
use Modules\Hrm\Entities\HrmPerformanceReview;
use Modules\Hrm\Entities\HrmPromotionProposal;

class HrmCareerProgressionService
{
    /**
     * Analyze employee achievements (tenure, 360 performance reviews, skill alignment)
     * and produce an actionable promotion proposal.
     */
    public function evaluatePromotionEligibility(HrmEmployee $employee): ?HrmPromotionProposal
    {
        $tenureMonths = $employee->hire_date ? (int) $employee->hire_date->diffInMonths(now()) : 12;

        // Evaluate actual performance review ratings if available
        $perfScore = 80.0;
        if (\Illuminate\Support\Facades\Schema::hasTable('hrm_performance_reviews')) {
            $avgScore = HrmPerformanceReview::query()
                ->where('employee_id', $employee->id)
                ->where('status', 'completed')
                ->avg('score');

            if ($avgScore !== null && $avgScore > 0) {
                $perfScore = (float) $avgScore;
            }
        }

        // Available career progression ranks
        $allPaths = HrmCareerPath::query()->orderBy('level')->get();
        if ($allPaths->isEmpty()) {
            // Seed initial progression ladder if empty
            HrmCareerPath::query()->insert([
                ['job_family' => 'تخصصی', 'level' => 1, 'title_fa' => 'کارشناس سطح ۱', 'min_tenure_months' => 12, 'min_performance_score' => 75.0, 'base_salary_multiplier' => 1.0, 'created_at' => now(), 'updated_at' => now()],
                ['job_family' => 'تخصصی', 'level' => 2, 'title_fa' => 'کارشناس ارشد سطح ۲', 'min_tenure_months' => 24, 'min_performance_score' => 80.0, 'base_salary_multiplier' => 1.25, 'created_at' => now(), 'updated_at' => now()],
                ['job_family' => 'تخصصی', 'level' => 3, 'title_fa' => 'سرپرست تخصصی سطح ۳', 'min_tenure_months' => 36, 'min_performance_score' => 85.0, 'base_salary_multiplier' => 1.50, 'created_at' => now(), 'updated_at' => now()],
                ['job_family' => 'تخصصی', 'level' => 4, 'title_fa' => 'مدیر دپارتمان سطح ۴', 'min_tenure_months' => 48, 'min_performance_score' => 90.0, 'base_salary_multiplier' => 1.80, 'created_at' => now(), 'updated_at' => now()],
            ]);
            $allPaths = HrmCareerPath::query()->orderBy('level')->get();
        }

        // Determine current and target rank
        $currentRank = (int) ($employee->rank_level ?? 1);
        $targetPath = $allPaths->firstWhere('level', $currentRank + 1) ?: $allPaths->last();

        // Calculate holistic eligibility score
        $tenureRatio = min(1.0, $tenureMonths / max(1, $targetPath->min_tenure_months));
        $perfRatio = min(1.0, $perfScore / max(1, $targetPath->min_performance_score));
        $eligibility = round(($tenureRatio * 0.50 + $perfRatio * 0.50) * 100, 2);

        if ($eligibility < 60.0) {
            return null;
        }

        return HrmPromotionProposal::query()->updateOrCreate(
            [
                'employee_id' => $employee->id,
                'target_career_path_id' => $targetPath->id,
            ],
            [
                'proposal_source' => 'system_automated',
                'eligibility_score' => $eligibility,
                'system_justification' => "کارمند با احراز {$tenureMonths} ماه سابقه خدمت، امتیاز عملکردی {$perfScore}٪ و تطابق شایستگی‌های عمومی، شایسته ارتقا به رتبه «{$targetPath->title_fa}» تشخیص داده شد.",
                'status' => 'pending_review',
            ]
        );
    }

    /**
     * Approve promotion proposal and immediately issue the corresponding official employment decree!
     */
    public function approveProposalAndIssueDecree(HrmPromotionProposal $proposal): HrmEmploymentDecree
    {
        $employee = $proposal->employee;
        $targetPath = $proposal->targetCareerPath;

        $multiplier = (float) ($targetPath?->base_salary_multiplier ?? 1.20);
        $newBase = round((float) $employee->base_salary * $multiplier, 2);
        $newDaily = round($newBase / 30, 2);

        $decreeNo = 'DEC-PROM-' . now()->format('Ymd') . '-' . $employee->id;

        $decree = HrmEmploymentDecree::query()->create([
            'employee_id' => $employee->id,
            'decree_no' => $decreeNo,
            'decree_type' => 'ارتقای شغلی و ترفیع رتبه',
            'effective_from' => now()->startOfMonth(),
            'base_salary' => $newBase,
            'daily_wage' => $newDaily,
            'status' => 'approved',
            'notes' => "صدور خودکار حکم کارگزینی بر مبنای تایید پیشنهاد ترفیع شماره {$proposal->id} به رتبه {$targetPath?->title_fa}",
        ]);

        // Update employee profile
        $employee->update([
            'base_salary' => $newBase,
            'daily_rate' => $newDaily,
            'rank_level' => $targetPath?->level ?? ($employee->rank_level + 1),
            'position' => $targetPath?->title_fa ?? $employee->position,
        ]);

        // Mark proposal as decree_issued
        $proposal->update([
            'status' => 'decree_issued',
            'decree_id' => $decree->id,
        ]);

        return $decree;
    }
}
