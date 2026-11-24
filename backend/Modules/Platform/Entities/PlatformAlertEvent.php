<?php

namespace Modules\Platform\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformAlertEvent extends Model
{
    protected $table = 'platform_alert_events';

    protected $fillable = [
        'rule_id',
        'server_id',
        'project_id',
        'resource_id',
        'status',
        'severity',
        'message',
        'metric_value',
        'opened_at',
        'acknowledged_at',
        'resolved_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'metric_value' => 'float',
            'opened_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(PlatformAlertRule::class, 'rule_id');
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(PlatformServer::class, 'server_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(PlatformProject::class, 'project_id');
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(PlatformResource::class, 'resource_id');
    }
}
