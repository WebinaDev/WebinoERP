<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmTrainingEnrollment extends Model
{
    protected $table = 'hrm_training_enrollments';

    protected $fillable = ['course_id', 'employee_id', 'status'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(HrmTrainingCourse::class, 'course_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'employee_id');
    }
}
