<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmEmployeeSalary extends Model
{
    protected $table = 'hrm_employee_salaries';

    protected $fillable = ['employee_id', 'base_salary', 'components', 'effective_from', 'effective_to'];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'components' => 'array',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'employee_id');
    }
}
