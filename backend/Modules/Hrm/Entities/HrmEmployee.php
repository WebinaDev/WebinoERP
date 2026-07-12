<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrmEmployee extends Model
{
    use SoftDeletes;

    protected $table = 'hrm_employees';

    protected $fillable = [
        'user_id', 'employee_code', 'first_name', 'last_name', 'email', 'mobile',
        'department', 'position', 'hire_date', 'status', 'base_salary', 'notes', 'created_by',
    ];

    protected $casts = [
        'hire_date' => 'date',
        'base_salary' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(HrmAttendanceRecord::class, 'employee_id');
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(HrmLeaveRequest::class, 'employee_id');
    }
}
