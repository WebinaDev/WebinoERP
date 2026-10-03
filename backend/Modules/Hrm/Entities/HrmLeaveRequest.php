<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmLeaveRequest extends Model
{
    protected $table = 'hrm_leave_requests';

    protected $fillable = [
        'employee_id', 'type', 'start_date', 'end_date', 'status', 'reason', 'approved_by',
        'approval_step', 'current_role', 'approval_log',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'approval_log' => 'array',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'employee_id');
    }
}
