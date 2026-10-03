<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrmPayrollRun extends Model
{
    protected $table = 'hrm_payroll_runs';

    protected $fillable = ['title', 'year', 'month', 'employee_id', 'status', 'total_amount', 'created_by', 'journal_entry_id', 'paid_at', 'serial_no'];

    protected $casts = ['total_amount' => 'decimal:2', 'paid_at' => 'datetime'];

    public function items(): HasMany
    {
        return $this->hasMany(HrmPayrollItem::class, 'payroll_run_id');
    }

    public function employee(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'employee_id');
    }
}
