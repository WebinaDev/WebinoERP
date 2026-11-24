<?php

namespace Modules\Platform\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformMetricSnapshot extends Model
{
    protected $table = 'platform_metric_snapshots';

    protected $fillable = [
        'server_id',
        'project_id',
        'resource_id',
        'scope',
        'metric_key',
        'value',
        'unit',
        'labels',
        'collected_at',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'float',
            'labels' => 'array',
            'collected_at' => 'datetime',
        ];
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
