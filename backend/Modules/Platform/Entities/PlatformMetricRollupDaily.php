<?php

namespace Modules\Platform\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformMetricRollupDaily extends Model
{
    protected $table = 'platform_metric_rollups_daily';

    protected $fillable = [
        'server_id',
        'project_id',
        'resource_id',
        'scope',
        'metric_key',
        'bucket_date',
        'value_avg',
        'value_max',
        'value_min',
        'sample_count',
        'unit',
        'labels',
    ];

    protected function casts(): array
    {
        return [
            'bucket_date' => 'date',
            'value_avg' => 'float',
            'value_max' => 'float',
            'value_min' => 'float',
            'sample_count' => 'integer',
            'labels' => 'array',
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
