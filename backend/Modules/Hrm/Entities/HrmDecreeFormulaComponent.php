<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;

class HrmDecreeFormulaComponent extends Model
{
    protected $table = 'hrm_decree_formula_components';

    protected $fillable = [
        'code',
        'title',
        'component_type',
        'calculation_type',
        'formula_expression',
        'is_taxable',
        'is_insurable',
        'is_active',
        'applicable_ranks',
    ];

    protected $casts = [
        'is_taxable' => 'boolean',
        'is_insurable' => 'boolean',
        'is_active' => 'boolean',
        'applicable_ranks' => 'array',
    ];
}
