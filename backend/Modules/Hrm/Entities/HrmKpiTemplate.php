<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;

class HrmKpiTemplate extends Model
{
    protected $table = 'hrm_kpi_templates';

    protected $fillable = ['name', 'description', 'criteria', 'is_active'];

    protected $casts = ['criteria' => 'array', 'is_active' => 'boolean'];
}
