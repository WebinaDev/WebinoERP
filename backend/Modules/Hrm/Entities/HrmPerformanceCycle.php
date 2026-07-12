<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;

class HrmPerformanceCycle extends Model
{
    protected $table = 'hrm_performance_cycles';

    protected $fillable = ['name', 'start_date', 'end_date', 'status'];

    protected $casts = ['start_date' => 'date', 'end_date' => 'date'];
}
