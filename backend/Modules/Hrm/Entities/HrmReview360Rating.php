<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmReview360Rating extends Model
{
    protected $table = 'hrm_review_360_ratings';

    protected $fillable = [
        'review_id', 'rater_employee_id', 'rater_user_id', 'relationship',
        'status', 'score', 'feedback', 'submitted_at',
    ];

    protected $casts = ['submitted_at' => 'datetime'];

    public function review(): BelongsTo
    {
        return $this->belongsTo(HrmReview360::class, 'review_id');
    }

    public function rater(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'rater_employee_id');
    }
}
