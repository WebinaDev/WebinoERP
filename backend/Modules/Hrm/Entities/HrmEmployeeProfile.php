<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmEmployeeProfile extends Model
{
    protected $table = 'hrm_employee_profiles';

    protected $fillable = [
        'employee_id', 'national_id', 'birth_date', 'gender', 'address',
        'emergency_contact', 'emergency_phone', 'custom_fields',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'custom_fields' => 'array',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'employee_id');
    }
}
