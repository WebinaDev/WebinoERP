<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrmReview360 extends Model
{
    protected $table = 'hrm_reviews_360';

    protected $fillable = ['employee_id', 'cycle_id', 'due_date', 'status', 'average_score', 'created_by'];

    protected $casts = [
        'due_date' => 'date',
        'average_score' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'employee_id');
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(HrmReview360Rating::class, 'review_id');
    }
}
