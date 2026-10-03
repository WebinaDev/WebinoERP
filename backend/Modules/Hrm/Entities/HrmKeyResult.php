<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmKeyResult extends Model
{
    protected $table = 'hrm_key_results';

    protected $fillable = ['objective_id', 'title', 'target_value', 'current_value', 'unit', 'progress'];

    protected $casts = [
        'target_value' => 'decimal:2',
        'current_value' => 'decimal:2',
    ];

    public function objective(): BelongsTo
    {
        return $this->belongsTo(HrmObjective::class, 'objective_id');
    }
}
