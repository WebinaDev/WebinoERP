<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmMissionOrder extends Model
{
    protected $table = 'hrm_mission_orders';

    protected $fillable = [
        'employee_id',
        'mission_no',
        'mission_type',
        'destination',
        'starts_at',
        'ends_at',
        'per_diem_rate',
        'transport_expense',
        'hotel_expense',
        'total_allowance',
        'status',
        'mission_report',
    ];

    protected $casts = [
        'per_diem_rate' => 'decimal:2',
        'transport_expense' => 'decimal:2',
        'hotel_expense' => 'decimal:2',
        'total_allowance' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'employee_id');
    }
}
