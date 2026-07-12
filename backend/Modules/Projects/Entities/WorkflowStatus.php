<?php

namespace Modules\Projects\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowStatus extends Model
{
    protected $table = 'prj_workflow_statuses';

    protected $fillable = ['name', 'color', 'sort_order'];

    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class, 'workflow_status_id');
    }
}
