<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;

class HrmShiftTemplate extends Model
{
    protected $table = 'hrm_shift_templates';

    protected $fillable = ['name', 'start_time', 'end_time', 'grace_minutes', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}
