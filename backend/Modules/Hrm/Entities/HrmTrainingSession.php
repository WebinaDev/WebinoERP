<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmTrainingSession extends Model
{
    protected $table = 'hrm_training_sessions';

    protected $fillable = ['course_id', 'title', 'starts_at', 'ends_at', 'location', 'status'];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(HrmTrainingCourse::class, 'course_id');
    }
}
