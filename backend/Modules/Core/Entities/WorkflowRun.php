<?php

namespace Modules\Core\Entities;

use Illuminate\Database\Eloquent\Model;

class WorkflowRun extends Model
{
    protected $table = 'core_workflow_runs';

    protected $fillable = [
        'workflow_id', 'status', 'context', 'log',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'log' => 'array',
        ];
    }
}
