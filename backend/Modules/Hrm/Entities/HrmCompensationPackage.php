<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;

class HrmCompensationPackage extends Model
{
    protected $table = 'hrm_compensation_packages';

    protected $fillable = [
        'code',
        'title',
        'employment_group',
        'base_ratio',
        'included_component_codes',
        'industry_rules',
        'is_active',
    ];

    protected $casts = [
        'base_ratio' => 'decimal:2',
        'included_component_codes' => 'array',
        'industry_rules' => 'array',
        'is_active' => 'boolean',
    ];
}
