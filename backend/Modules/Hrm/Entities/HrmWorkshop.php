<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;

class HrmWorkshop extends Model
{
    protected $table = 'hrm_workshops';

    protected $fillable = [
        'company_code',
        'company_name',
        'workshop_code',
        'workshop_name',
        'employer_name',
        'tax_tfn',
        'postal_code',
        'address',
        'sso_contract_row',
        'employer_rate',
        'is_active',
    ];

    protected $casts = [
        'employer_rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
