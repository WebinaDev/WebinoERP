<?php

namespace Modules\Projects\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrjTaskTemplate extends Model
{
    protected $table = 'prj_task_templates';

    protected $fillable = [
        'project_id',
        'title',
        'description',
        'payload',
        'schedule',
        'next_run_at',
        'copy_checklists',
        'copy_assignees',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'schedule' => 'array',
            'next_run_at' => 'datetime',
            'copy_checklists' => 'boolean',
            'copy_assignees' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
