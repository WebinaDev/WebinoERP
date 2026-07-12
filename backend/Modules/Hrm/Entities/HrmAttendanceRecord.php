<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmAttendanceRecord extends Model
{
    protected $table = 'hrm_attendance_records';

    protected $fillable = ['employee_id', 'date', 'check_in', 'check_out', 'status', 'notes'];

    protected $casts = ['date' => 'date'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'employee_id');
    }
}
