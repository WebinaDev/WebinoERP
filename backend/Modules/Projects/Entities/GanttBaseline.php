<?php

namespace Modules\Projects\Entities;

use Illuminate\Database\Eloquent\Model;

class GanttBaseline extends Model
{
    protected $table = 'prj_gantt_baselines';

    protected $fillable = [
        'project_id', 'name', 'snapshot', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
        ];
    }
}
