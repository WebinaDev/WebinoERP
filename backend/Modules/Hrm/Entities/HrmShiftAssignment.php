<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmShiftAssignment extends Model
{
    protected $table = 'hrm_shift_assignments';

    protected $fillable = [
        'employee_id', 'shift_template_id', 'work_date', 'rotation_id', 'source',
        'is_off', 'notes', 'created_by',
    ];

    protected $casts = [
        'work_date' => 'date',
        'is_off' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'employee_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(HrmShiftTemplate::class, 'shift_template_id');
    }

    public function rotation(): BelongsTo
    {
        return $this->belongsTo(HrmShiftRotation::class, 'rotation_id');
    }
}
