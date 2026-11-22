<?php

namespace Modules\Hrm\Services;

use Modules\Hrm\Entities\HrmAttritionRiskLog;
use Modules\Hrm\Entities\HrmEmployee;

class HrmAttritionPredictionService
{
    /**
     * Compute employee attrition risk index based on overtime hours, tenure without promotion, and leave usage.
     */
    public function evaluateEmployeeRisk(HrmEmployee $employee): HrmAttritionRiskLog
    {
        $factors = [];
        $score = 15.0; // base low attrition risk

        // 1. Overtime hours check
        $overtimeCount = 0;
        if (\Illuminate\Support\Facades\Schema::hasTable('hrm_requests')) {
            $overtimeCount = \Modules\Hrm\Entities\HrmRequest::query()
                ->where('employee_id', $employee->id)
                ->where('type', 'overtime')
                ->where('status', 'approved')
                ->count();
        }

        if ($overtimeCount > 15) {
            $score += 25.0;
            $factors[] = 'اضافه‌کار بیش از حد استاندارد و خطر فرسودگی شغلی (Burnout)';
        }

        // 2. Promotion stagnation check
        $latestDecree = null;
        if (\Illuminate\Support\Facades\Schema::hasTable('hrm_employment_decrees')) {
            $latestDecree = \Modules\Hrm\Entities\HrmEmploymentDecree::query()
                ->where('employee_id', $employee->id)
                ->orderByDesc('effective_from')
                ->first();
        }

        if ($latestDecree && $latestDecree->effective_from && $latestDecree->effective_from->diffInYears(now()) >= 2) {
            $score += 20.0;
            $factors[] = 'عدم بازنگری رتبه یا حکم شغلی در ۲ سال گذشته';
        }

        // 3. Compensation benchmark
        if ((float) $employee->base_salary < 180000000) {
            $score += 15.0;
            $factors[] = 'سطح حقوق پایه پایین‌تر از میانه بازار کار در این رده تخصص';
        }

        $probability = min(95.0, max(5.0, round($score, 2)));

        $riskLevel = match (true) {
            $probability >= 70 => 'critical',
            $probability >= 50 => 'high',
            $probability >= 30 => 'medium',
            default => 'low',
        };

        $recommendations = match ($riskLevel) {
            'critical' => 'جلسه فوری 1-on-1 با مدیر مستقیم، بازنگری بسته پاداش و کاهش ساعات اضافه‌کار',
            'high' => 'بررسی ارتقای شغلی، تخصیص بودجه آموزشی و ارائه بسته مزایای رفاهی تکمیلی',
            'medium' => 'نظرسنجی رضایت شغلی ۳۶۰ درجه و برنامه‌ریزی مصاحبه نگهداشت',
            default => 'وضعیت مطلوب؛ ادامه تعاملات منظم فصلی',
        };

        return HrmAttritionRiskLog::query()->create([
            'employee_id' => $employee->id,
            'risk_level' => $riskLevel,
            'risk_probability' => $probability,
            'top_contributing_factors' => $factors,
            'recommended_actions' => $recommendations,
            'calculated_date' => now()->toDateString(),
        ]);
    }
}
