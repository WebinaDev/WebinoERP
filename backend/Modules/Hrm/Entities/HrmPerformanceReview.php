<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmPerformanceReview extends Model
{
    protected $table = 'hrm_performance_reviews';

    protected $fillable = ['employee_id', 'period', 'score', 'feedback', 'reviewer_id'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'employee_id');
    }
}
