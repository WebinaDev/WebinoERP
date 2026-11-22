<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmEmployeeSettlement extends Model
{
    protected $table = 'hrm_employee_settlements';

    protected $fillable = [
        'employee_id',
        'settlement_date',
        'reason',
        'tenure_years',
        'severance_amount',
        'unused_leave_days',
        'leave_buyback_amount',
        'pro_rated_eydi_amount',
        'final_net_payable',
        'status',
    ];

    protected $casts = [
        'settlement_date' => 'date',
        'tenure_years' => 'decimal:2',
        'severance_amount' => 'decimal:2',
        'unused_leave_days' => 'decimal:2',
        'leave_buyback_amount' => 'decimal:2',
        'pro_rated_eydi_amount' => 'decimal:2',
        'final_net_payable' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'employee_id');
    }
}
