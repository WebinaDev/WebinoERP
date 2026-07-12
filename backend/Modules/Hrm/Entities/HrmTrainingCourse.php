<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrmTrainingCourse extends Model
{
    protected $table = 'hrm_training_courses';

    protected $fillable = ['title', 'description', 'start_date', 'end_date', 'status'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function enrollments(): HasMany
    {
        return $this->hasMany(HrmTrainingEnrollment::class, 'course_id');
    }
}
