<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmSeveranceReserve extends Model
{
    protected $table = 'hrm_severance_reserves';

    protected $fillable = [
        'employee_id',
        'payroll_run_id',
        'year',
        'month',
        'monthly_provision',
        'cumulative_balance',
        'status',
    ];

    protected $casts = [
        'monthly_provision' => 'decimal:2',
        'cumulative_balance' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'employee_id');
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(HrmPayrollRun::class, 'payroll_run_id');
    }
}
