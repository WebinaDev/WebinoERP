<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmPayrollRetroAdjustment extends Model
{
    protected $table = 'hrm_payroll_retro_adjustments';

    protected $fillable = [
        'employee_id',
        'decree_id',
        'payroll_run_id',
        'target_year',
        'target_month',
        'original_gross',
        'adjusted_gross',
        'differential_amount',
        'differential_insurance',
        'differential_tax',
        'differential_net',
        'status',
        'reason',
        'metadata',
    ];

    protected $casts = [
        'original_gross' => 'decimal:2',
        'adjusted_gross' => 'decimal:2',
        'differential_amount' => 'decimal:2',
        'differential_insurance' => 'decimal:2',
        'differential_tax' => 'decimal:2',
        'differential_net' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'employee_id');
    }

    public function decree(): BelongsTo
    {
        return $this->belongsTo(HrmEmploymentDecree::class, 'decree_id');
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(HrmPayrollRun::class, 'payroll_run_id');
    }
}
