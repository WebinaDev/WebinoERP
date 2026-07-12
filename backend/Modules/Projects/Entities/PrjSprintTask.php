<?php

namespace Modules\Projects\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrjSprintTask extends Model
{
    protected $table = 'prj_sprint_tasks';

    protected $fillable = [
        'sprint_id',
        'project_task_id',
        'sort_order',
    ];

    public function sprint(): BelongsTo
    {
        return $this->belongsTo(PrjSprint::class, 'sprint_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'project_task_id');
    }
}
