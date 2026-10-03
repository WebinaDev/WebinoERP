<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrmObjective extends Model
{
    protected $table = 'hrm_objectives';

    protected $fillable = [
        'employee_id', 'cycle_id', 'parent_id', 'title', 'description', 'period',
        'weight', 'progress', 'status',
    ];

    protected $casts = ['weight' => 'decimal:2'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'employee_id');
    }

    public function keyResults(): HasMany
    {
        return $this->hasMany(HrmKeyResult::class, 'objective_id');
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(HrmPerformanceCycle::class, 'cycle_id');
    }
}
