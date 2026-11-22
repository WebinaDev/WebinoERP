<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmTrainingNeedsAssessment extends Model
{
    protected $table = 'hrm_training_needs_assessments';

    protected $fillable = [
        'employee_id',
        'course_id',
        'competency_title',
        'required_proficiency_level',
        'current_proficiency_level',
        'gap_score',
        'priority',
        'status',
    ];

    protected $casts = [
        'required_proficiency_level' => 'decimal:2',
        'current_proficiency_level' => 'decimal:2',
        'gap_score' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'employee_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(HrmTrainingCourse::class, 'course_id');
    }
}
