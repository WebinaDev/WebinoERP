<?php

namespace Modules\Projects\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrjEpic extends Model
{
    protected $table = 'prj_epics';

    protected $fillable = [
        'project_id',
        'title',
        'description',
        'state',
        'start_date',
        'end_date',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class, 'epic_id');
    }
}
