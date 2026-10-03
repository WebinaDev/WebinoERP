<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmLessonProgress extends Model
{
    protected $table = 'hrm_lesson_progress';

    protected $fillable = ['lesson_id', 'employee_id', 'enrollment_id', 'progress_percent', 'completed_at'];

    protected $casts = ['completed_at' => 'datetime'];

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(HrmTrainingLesson::class, 'lesson_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'employee_id');
    }
}
