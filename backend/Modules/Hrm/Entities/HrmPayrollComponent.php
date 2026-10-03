<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;

class HrmPayrollComponent extends Model
{
    protected $table = 'hrm_payroll_components';

    protected $fillable = ['name', 'code', 'type', 'calculation', 'default_amount', 'is_active', 'is_insurable', 'is_taxable'];

    protected $casts = ['default_amount' => 'decimal:2', 'is_active' => 'boolean', 'is_insurable' => 'boolean', 'is_taxable' => 'boolean'];
}
