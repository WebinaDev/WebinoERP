<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmRetireeRecord extends Model
{
    protected $table = 'hrm_retiree_records';

    protected $fillable = [
        'employee_id',
        'retirement_date',
        'pension_fund_type',
        'final_severance_amount',
        'unused_leave_buyback_amount',
        'pension_supplement_monthly',
        'status',
        'survivors_data',
    ];

    protected $casts = [
        'final_severance_amount' => 'decimal:2',
        'unused_leave_buyback_amount' => 'decimal:2',
        'pension_supplement_monthly' => 'decimal:2',
        'survivors_data' => 'array',
        'retirement_date' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'employee_id');
    }
}
