<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmTimesheet extends Model
{
    protected $table = 'hrm_timesheets';

    protected $fillable = [
        'employee_id', 'project_id', 'task_id', 'project_name', 'task_name',
        'work_date', 'hours', 'description', 'status', 'submitted_at',
        'approved_by', 'approved_at', 'decision_note',
        'approval_step', 'current_role', 'approval_log',
    ];

    protected $casts = [
        'approval_log' => 'array',
        'work_date' => 'date',
        'hours' => 'decimal:2',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'employee_id');
    }
}
