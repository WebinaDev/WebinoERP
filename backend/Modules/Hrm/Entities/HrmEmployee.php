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
        'department', 'position', 'hire_date', 'status', 'base_salary', 'notes',
        'shift_template_id', 'created_by',
    ];

    protected $casts = [
        'hire_date' => 'date',
        'base_salary' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function profile(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(HrmEmployeeProfile::class, 'employee_id');
    }

    public function shiftTemplate(): BelongsTo
    {
        return $this->belongsTo(HrmShiftTemplate::class, 'shift_template_id');
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(HrmAttendanceRecord::class, 'employee_id');
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(HrmLeaveRequest::class, 'employee_id');
    }

    public function dependents(): HasMany
    {
        return $this->hasMany(HrmDependent::class, 'employee_id');
    }

    public function decrees(): HasMany
    {
        return $this->hasMany(HrmEmploymentDecree::class, 'employee_id');
    }

    public function payrollItems(): HasMany
    {
        return $this->hasMany(HrmPayrollItem::class, 'employee_id');
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(HrmLeaveBalance::class, 'employee_id');
    }
}
