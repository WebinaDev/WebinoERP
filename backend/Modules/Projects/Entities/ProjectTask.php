<?php

namespace Modules\Projects\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Entities\CoreTaskCategory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectTask extends Model
{
    protected $table = 'prj_tasks';

    protected $fillable = [
        'project_id',
        'title',
        'status',
        'priority',
        'workflow_status_id',
        'category_id',
        'label',
        'content',
        'sprint_id',
        'epic_id',
        'checklist',
        'time_logs',
        'recurrence',
        'assignee_id',
        'due_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'checklist' => 'array',
            'time_logs' => 'array',
            'recurrence' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function workflowStatus(): BelongsTo
    {
        return $this->belongsTo(WorkflowStatus::class, 'workflow_status_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CoreTaskCategory::class, 'category_id');
    }

    public function sprint(): BelongsTo
    {
        return $this->belongsTo(PrjSprint::class, 'sprint_id');
    }

    public function epic(): BelongsTo
    {
        return $this->belongsTo(PrjEpic::class, 'epic_id');
    }
}
