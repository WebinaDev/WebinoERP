<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrmSubsidiaryCompany extends Model
{
    protected $table = 'hrm_subsidiary_companies';

    protected $fillable = [
        'code',
        'name',
        'tax_tfn',
        'postal_code',
        'company_sheba',
        'workshop_code',
        'employer_name',
        'payroll_policy_model',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function payrollRuns(): HasMany
    {
        return $this->hasMany(HrmPayrollRun::class, 'subsidiary_company_id');
    }
}
