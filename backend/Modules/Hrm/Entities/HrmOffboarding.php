<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrmOffboarding extends Model
{
    protected $table = 'hrm_offboardings';

    protected $fillable = [
        'employee_id', 'template_id', 'status', 'reason', 'exit_interview_notes', 'last_day',
        'started_at', 'completed_at',
    ];

    protected $casts = [
        'last_day' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $appends = ['progress'];

    /**
     * @return array{done: int, total: int, percent: int}
     */
    public function getProgressAttribute(): array
    {
        $tasks = $this->relationLoaded('tasks') ? $this->tasks : $this->tasks()->get();
        $total = $tasks->count();
        $done = $tasks->whereIn('status', ['done', 'skipped'])->count();

        return [
            'done' => $done,
            'total' => $total,
            'percent' => $total > 0 ? (int) round($done * 100 / $total) : 0,
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'employee_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(HrmOffboardingTemplate::class, 'template_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(HrmOffboardingTask::class, 'offboarding_id')->orderBy('sort_order');
    }
}
