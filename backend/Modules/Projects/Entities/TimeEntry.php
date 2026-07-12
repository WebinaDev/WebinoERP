<?php

namespace Modules\Projects\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeEntry extends Model
{
    protected $table = 'prj_time_entries';

    protected $fillable = [
        'user_id',
        'task_id',
        'project_id',
        'started_at',
        'ended_at',
        'paused_at',
        'duration_seconds',
        'is_running',
        'is_billable',
        'description',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'paused_at' => 'datetime',
            'duration_seconds' => 'integer',
            'is_running' => 'boolean',
            'is_billable' => 'boolean',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'task_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }
}
