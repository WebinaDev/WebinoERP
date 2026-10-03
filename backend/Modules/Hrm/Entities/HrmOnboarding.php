<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrmOnboarding extends Model
{
    protected $table = 'hrm_onboardings';

    protected $fillable = [
        'employee_id', 'template_id', 'applicant_id', 'status', 'started_at', 'completed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'employee_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(HrmOnboardingTemplate::class, 'template_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(HrmOnboardingTask::class, 'onboarding_id')->orderBy('sort_order');
    }
}
