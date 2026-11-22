<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmPerformanceAgreement extends Model
{
    protected $table = 'hrm_performance_agreements';

    protected $fillable = [
        'employee_id',
        'manager_id',
        'cycle_id',
        'agreement_code',
        'start_date',
        'end_date',
        'kpi_targets',
        'competency_targets',
        'midterm_progress_score',
        'final_score',
        'status',
        'manager_signed_at',
        'employee_signed_at',
    ];

    protected $casts = [
        'kpi_targets' => 'array',
        'competency_targets' => 'array',
        'midterm_progress_score' => 'decimal:2',
        'final_score' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'manager_signed_at' => 'datetime',
        'employee_signed_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'employee_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'manager_id');
    }
}
