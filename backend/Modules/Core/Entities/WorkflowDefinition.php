<?php

namespace Modules\Core\Entities;

use Illuminate\Database\Eloquent\Model;

class WorkflowDefinition extends Model
{
    protected $table = 'core_workflows';

    protected $fillable = [
        'name', 'trigger_key', 'graph', 'status',
    ];

    protected function casts(): array
    {
        return [
            'graph' => 'array',
        ];
    }
}
