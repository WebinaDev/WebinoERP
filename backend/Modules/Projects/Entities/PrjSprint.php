<?php

namespace Modules\Projects\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrjSprint extends Model
{
    protected $table = 'prj_sprints';

    protected $fillable = [
        'project_id', 'name', 'starts_at', 'ends_at', 'status',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class, 'sprint_id');
    }

    public function sprintTasks(): BelongsToMany
    {
        return $this->belongsToMany(ProjectTask::class, 'prj_sprint_tasks', 'sprint_id', 'project_task_id')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }
}
