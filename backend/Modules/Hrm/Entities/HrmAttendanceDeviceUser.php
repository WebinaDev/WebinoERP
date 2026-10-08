<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Maps a terminal's enrolled user id (PIN) to an employee. device_id null = any device. */
class HrmAttendanceDeviceUser extends Model
{
    protected $table = 'hrm_attendance_device_users';

    protected $fillable = ['device_id', 'device_user_id', 'employee_id'];

    public function device(): BelongsTo
    {
        return $this->belongsTo(HrmAttendanceDevice::class, 'device_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'employee_id');
    }
}
