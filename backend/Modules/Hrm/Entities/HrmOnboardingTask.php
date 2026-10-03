<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmOnboardingTask extends Model
{
    protected $table = 'hrm_onboarding_tasks';

    protected $fillable = [
        'onboarding_id', 'title', 'description', 'document_category', 'document_id',
        'status', 'due_date', 'completed_at', 'owner', 'sort_order',
    ];

    protected $casts = [
        'due_date' => 'date',
        'completed_at' => 'datetime',
    ];

    public function onboarding(): BelongsTo
    {
        return $this->belongsTo(HrmOnboarding::class, 'onboarding_id');
    }
}
