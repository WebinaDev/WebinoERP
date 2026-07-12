<?php

namespace Modules\Projects\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskLink extends Model
{
    protected $table = 'prj_task_links';

    protected $fillable = ['source_task_id', 'target_task_id', 'link_type'];

    public function sourceTask(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'source_task_id');
    }

    public function targetTask(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'target_task_id');
    }
}
