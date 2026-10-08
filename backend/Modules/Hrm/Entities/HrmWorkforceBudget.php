<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;

class HrmWorkforceBudget extends Model
{
    protected $table = 'hrm_workforce_budgets';

    protected $fillable = ['department', 'year', 'month', 'headcount', 'cost_budget', 'notes'];

    protected $casts = [
        'cost_budget' => 'decimal:2',
    ];
}
