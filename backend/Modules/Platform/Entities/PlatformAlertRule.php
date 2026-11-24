<?php

namespace Modules\Platform\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlatformAlertRule extends Model
{
    protected $table = 'platform_alert_rules';

    protected $fillable = [
        'name',
        'scope_type',
        'scope_id',
        'metric_key',
        'operator',
        'threshold',
        'duration_seconds',
        'severity',
        'channel_ids',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'threshold' => 'float',
            'duration_seconds' => 'integer',
            'channel_ids' => 'array',
            'is_enabled' => 'boolean',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(PlatformAlertEvent::class, 'rule_id');
    }
}
