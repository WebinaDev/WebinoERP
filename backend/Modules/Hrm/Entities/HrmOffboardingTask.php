<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmOffboardingTask extends Model
{
    protected $table = 'hrm_offboarding_tasks';

    protected $fillable = [
        'offboarding_id', 'title', 'description', 'kind', 'owner', 'status', 'notes',
        'asset_label', 'required', 'sort_order', 'completed_at',
    ];

    protected $casts = [
        'required' => 'boolean',
        'completed_at' => 'datetime',
    ];

    public function offboarding(): BelongsTo
    {
        return $this->belongsTo(HrmOffboarding::class, 'offboarding_id');
    }
}
