<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrmTrainingLesson extends Model
{
    protected $table = 'hrm_training_lessons';

    protected $fillable = [
        'course_id', 'title', 'body', 'content_type', 'material_url',
        'duration_minutes', 'sort_order', 'is_required',
    ];

    protected $casts = ['is_required' => 'boolean'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(HrmTrainingCourse::class, 'course_id');
    }

    public function progress(): HasMany
    {
        return $this->hasMany(HrmLessonProgress::class, 'lesson_id');
    }
}
