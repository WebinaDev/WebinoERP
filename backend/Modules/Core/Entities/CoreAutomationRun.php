<?php

namespace Modules\Core\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoreAutomationRun extends Model
{
    protected $table = 'core_automation_runs';

    protected $fillable = [
        'rule_id',
        'status',
        'event_payload',
        'result_payload',
        'error',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'event_payload' => 'array',
            'result_payload' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(CoreAutomationRule::class, 'rule_id');
    }
}
