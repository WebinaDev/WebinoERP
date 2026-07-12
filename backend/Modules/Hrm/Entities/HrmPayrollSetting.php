<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;

class HrmPayrollSetting extends Model
{
    protected $table = 'hrm_payroll_settings';

    protected $fillable = ['key', 'value'];

    protected $casts = ['value' => 'array'];
}
