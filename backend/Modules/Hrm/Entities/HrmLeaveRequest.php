<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmLeaveRequest extends Model
{
    protected $table = 'hrm_leave_requests';

    protected $fillable = [
        'employee_id', 'leave_type_id', 'type', 'start_date', 'end_date', 'starts_at', 'ends_at',
        'status', 'reason', 'rejection_reason', 'approved_by',
        'approval_step', 'current_role', 'approval_log',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'approval_log' => 'array',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'employee_id');
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(HrmLeaveType::class, 'leave_type_id');
    }
}
