<?php

namespace Modules\Hrm\Services;

use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmTrainingCourse;
use Modules\Hrm\Entities\HrmTrainingNeedsAssessment;

class HrmTalentAndCompetencyService
{
    /**
     * Conduct competence gap analysis and suggest training courses
     *
     * @return HrmTrainingNeedsAssessment
     */
    public function assessCompetencyGap(
        HrmEmployee $employee,
        string $competencyTitle,
        float $requiredLevel,
        float $currentLevel,
        ?int $courseId = null
    ): HrmTrainingNeedsAssessment {
        $gap = max(0.0, round($requiredLevel - $currentLevel, 2));
        $priority = match (true) {
            $gap >= 2.0 => 'critical',
            $gap >= 1.0 => 'high',
            $gap > 0.0 => 'medium',
            default => 'low',
        };

        return HrmTrainingNeedsAssessment::query()->updateOrCreate(
            [
                'employee_id' => $employee->id,
                'competency_title' => $competencyTitle,
            ],
            [
                'course_id' => $courseId,
                'required_proficiency_level' => $requiredLevel,
                'current_proficiency_level' => $currentLevel,
                'gap_score' => $gap,
                'priority' => $priority,
                'status' => 'identified',
            ]
        );
    }
}
