<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmPayrollItem extends Model
{
    protected $table = 'hrm_payroll_items';

    protected $fillable = ['payroll_run_id', 'employee_id', 'gross', 'deductions', 'net'];

    protected $casts = [
        'gross' => 'decimal:2',
        'deductions' => 'decimal:2',
        'net' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'employee_id');
    }
}
