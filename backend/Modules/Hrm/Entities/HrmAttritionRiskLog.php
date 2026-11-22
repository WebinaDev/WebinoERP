<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmAttritionRiskLog extends Model
{
    protected $table = 'hrm_attrition_risk_logs';

    protected $fillable = [
        'employee_id',
        'risk_level',
        'risk_probability',
        'top_contributing_factors',
        'recommended_actions',
        'calculated_date',
    ];

    protected $casts = [
        'risk_probability' => 'decimal:2',
        'top_contributing_factors' => 'array',
        'calculated_date' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'employee_id');
    }
}
