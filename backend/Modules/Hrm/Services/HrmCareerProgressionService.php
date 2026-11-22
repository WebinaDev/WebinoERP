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
     * Analyze employee achievements and produce promotion proposals based exclusively on configured career paths.
     * Incorporates official performance review agreements and weighted KPIs.
     */
    public function evaluatePromotionEligibility(HrmEmployee $employee): ?HrmPromotionProposal
    {
        $tenureMonths = $employee->hire_date ? (int) $employee->hire_date->diffInMonths(now()) : 12;

        $perfScore = 80.0;

        // 1. Check formal performance agreements if present
        if (\Illuminate\Support\Facades\Schema::hasTable('hrm_performance_agreements')) {
            $lastAgreement = \Illuminate\Support\Facades\DB::table('hrm_performance_agreements')
                ->where('employee_id', $employee->id)
                ->where('stage', 'finalized')
                ->orderByDesc('id')
                ->first();

            if ($lastAgreement && $lastAgreement->final_score !== null) {
                $perfScore = (float) $lastAgreement->final_score;
            }
        } elseif (\Illuminate\Support\Facades\Schema::hasTable('hrm_performance_reviews')) {
            $avgScore = HrmPerformanceReview::query()
                ->where('employee_id', $employee->id)
                ->where('status', 'completed')
                ->avg('score');

            if ($avgScore !== null && $avgScore > 0) {
                $perfScore = (float) $avgScore;
            }
        }

        $allPaths = HrmCareerPath::query()->orderBy('level')->get();
        if ($allPaths->isEmpty()) {
            return null; // Do not auto-seed dummy paths inside business logic
        }

        $currentRank = (int) ($employee->rank_level ?? 1);
        $targetPath = $allPaths->firstWhere('level', $currentRank + 1) ?: $allPaths->last();

        $tenureRatio = min(1.0, $tenureMonths / max(1, $targetPath->min_tenure_months));
        $perfRatio = min(1.0, $perfScore / max(1, $targetPath->min_performance_score));
        $eligibility = round(($tenureRatio * 0.50 + $perfRatio * 0.50) * 100, 2);

        if ($eligibility < 60.0) {
            return null;
        }

        $formattedYears = round($tenureMonths / 12, 1);
        $justification = "بر اساس ارزیابی عملکرد دوره‌ای با نمره مکتسبه {$perfScore} و احراز {$formattedYears} سال سابقه خدمت مرتبط، کارمند واجد شرایط ارتقای رتبه سازمانی به عنوان «{$targetPath->title_fa}» تشخیص داده شد.";

        return HrmPromotionProposal::query()->updateOrCreate(
            [
                'employee_id' => $employee->id,
                'target_career_path_id' => $targetPath->id,
            ],
            [
                'proposal_source' => 'system_automated',
                'eligibility_score' => $eligibility,
                'system_justification' => $justification,
                'status' => 'pending_review',
            ]
        );
    }

    /**
     * Approve promotion proposal and immediately issue official employment decree with enterprise serial number.
     */
    public function approveProposalAndIssueDecree(HrmPromotionProposal $proposal): HrmEmploymentDecree
    {
        $employee = $proposal->employee;
        $targetPath = $proposal->targetCareerPath;

        $multiplier = (float) ($targetPath?->base_salary_multiplier ?? 1.20);
        $newBase = round((float) $employee->base_salary * $multiplier, 2);
        $newDaily = round($newBase / 30, 2);

        // Generate official Iranian fiscal serial number (e.g. 1405/HR/00124)
        $serialService = app(HrmSerialService::class);
        $decreeNo = $serialService->next('decree');

        $decree = HrmEmploymentDecree::query()->create([
            'employee_id' => $employee->id,
            'decree_no' => $decreeNo,
            'decree_type' => 'ارتقای شغلی و ترفیع رتبه',
            'effective_from' => now()->startOfMonth(),
            'base_salary' => $newBase,
            'daily_wage' => $newDaily,
            'status' => 'approved',
            'notes' => "صدور حکم اداری پیرو تایید پیشنهاد ارتقای رتبه شغلی به «{$targetPath?->title_fa}»",
        ]);

        $employee->update([
            'base_salary' => $newBase,
            'daily_rate' => $newDaily,
            'rank_level' => $targetPath?->level ?? ($employee->rank_level + 1),
            'position' => $targetPath?->title_fa ?? $employee->position,
        ]);

        $proposal->update([
            'status' => 'decree_issued',
            'decree_id' => $decree->id,
        ]);

        return $decree;
    }
}
