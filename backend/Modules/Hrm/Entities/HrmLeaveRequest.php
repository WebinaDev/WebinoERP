<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmLeaveRequest extends Model
{
    protected $table = 'hrm_leave_requests';

    protected $fillable = ['employee_id', 'type', 'start_date', 'end_date', 'status', 'reason', 'approved_by'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'employee_id');
    }
}
