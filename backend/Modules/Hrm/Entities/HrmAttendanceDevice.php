<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrmAttendanceDevice extends Model
{
    protected $table = 'hrm_attendance_devices';

    protected $fillable = [
        'name', 'device_code', 'api_key_hash', 'vendor', 'location', 'is_active', 'last_seen_at',
    ];

    protected $hidden = ['api_key_hash'];

    protected $casts = [
        'is_active' => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    public function punches(): HasMany
    {
        return $this->hasMany(HrmAttendancePunch::class, 'device_id');
    }
}
