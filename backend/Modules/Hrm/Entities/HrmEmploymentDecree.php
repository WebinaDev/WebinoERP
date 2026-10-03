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
        'contract_type', 'engagement_type', 'pay_basis', 'job_code', 'base_salary', 'daily_wage',
        'project_fee', 'hourly_rate', 'insurance_applicable', 'tax_applicable', 'benefits', 'workshop_id',
        'serial_no', 'signer_name', 'signer_role', 'stamp_path',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
        'base_salary' => 'decimal:2',
        'daily_wage' => 'decimal:2',
        'project_fee' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
        'insurance_applicable' => 'boolean',
        'tax_applicable' => 'boolean',
        'benefits' => 'array',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'employee_id');
    }
}
