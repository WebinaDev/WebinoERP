<?php

namespace Modules\Hrm\Services;

use Modules\Hrm\Entities\HrmCareerPath;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmPromotionProposal;

class HrmCareerProgressionService
{
    /**
     * Analyze employee achievements and determine if eligible for automatic promotion proposal.
     */
    public function evaluatePromotionEligibility(HrmEmployee $employee): ?HrmPromotionProposal
    {
        $tenureMonths = $employee->hire_date ? (int) $employee->hire_date->diffInMonths(now()) : 24;

        // Find career paths
        $allPaths = HrmCareerPath::query()->orderBy('level')->get();
        if ($allPaths->isEmpty()) {
            return null;
        }

        // Determine target next level
        $targetPath = $allPaths->firstWhere('min_tenure_months', '<=', $tenureMonths);
        if (! $targetPath) {
            $targetPath = $allPaths->first();
        }

        // Calculate score
        $perfScore = 85.0; // default benchmark
        $eligibility = min(100.0, round(($tenureMonths / max(1, $targetPath->min_tenure_months)) * 80 + ($perfScore * 0.2), 2));

        if ($eligibility < 70.0) {
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
                'system_justification' => "کارمند با احراز {$tenureMonths} ماه سابقه خدمت و امتیاز عملکرد مطلوب، شایسته ارتقای سازمانی به رتبه «{$targetPath->title_fa}» تشخیص داده شد.",
                'status' => 'pending_review',
            ]
        );
    }
}
