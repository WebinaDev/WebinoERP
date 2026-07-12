<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;

class HrmLeaveType extends Model
{
    protected $table = 'hrm_leave_types';

    protected $fillable = ['name', 'default_days', 'is_paid', 'is_active'];

    protected $casts = ['is_paid' => 'boolean', 'is_active' => 'boolean'];
}
