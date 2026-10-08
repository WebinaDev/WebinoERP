<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmAttendancePunch extends Model
{
    protected $table = 'hrm_attendance_punches';

    protected $fillable = [
        'device_id', 'employee_id', 'raw_code', 'national_id', 'punched_at', 'direction',
        'source', 'status', 'conflict_note', 'attendance_record_id', 'payload',
    ];

    protected $casts = [
        'punched_at' => 'datetime',
        'payload' => 'array',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(HrmAttendanceDevice::class, 'device_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'employee_id');
    }
}
