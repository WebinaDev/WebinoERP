<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmEmploymentDecree extends Model
{
    protected $table = 'hrm_employment_decrees';

    protected $fillable = [
        'employee_id', 'user_id', 'decree_no', 'decree_type', 'status',
        'effective_from', 'effective_to', 'job_title', 'department',
        'contract_type', 'job_code', 'base_salary', 'daily_wage', 'workshop_id',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
        'base_salary' => 'decimal:2',
        'daily_wage' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'employee_id');
    }
}
