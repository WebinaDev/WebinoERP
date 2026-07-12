<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmLeaveBalance extends Model
{
    protected $table = 'hrm_leave_balances';

    protected $fillable = ['employee_id', 'leave_type_id', 'year', 'allocated', 'used'];

    protected $casts = ['allocated' => 'decimal:2', 'used' => 'decimal:2'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'employee_id');
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(HrmLeaveType::class, 'leave_type_id');
    }
}
