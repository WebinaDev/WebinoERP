<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmAttendanceCorrectionRequest extends Model
{
    protected $table = 'hrm_attendance_correction_requests';

    protected $fillable = [
        'employee_id',
        'record_date',
        'requested_clock_in',
        'requested_clock_out',
        'reason_type',
        'description',
        'status',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'record_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'employee_id');
    }
}
