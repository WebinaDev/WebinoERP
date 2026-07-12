<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrmPayrollRun extends Model
{
    protected $table = 'hrm_payroll_runs';

    protected $fillable = ['title', 'year', 'month', 'status', 'total_amount', 'created_by'];

    protected $casts = ['total_amount' => 'decimal:2'];

    public function items(): HasMany
    {
        return $this->hasMany(HrmPayrollItem::class, 'payroll_run_id');
    }
}
