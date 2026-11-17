<?php

namespace Modules\Platform\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformUptimeCheck extends Model
{
    protected $table = 'platform_uptime_checks';

    protected $fillable = [
        'domain_id',
        'url',
        'http_status',
        'response_time_ms',
        'is_healthy',
        'error_message',
        'checked_at',
    ];

    protected $casts = [
        'is_healthy' => 'boolean',
        'http_status' => 'integer',
        'response_time_ms' => 'integer',
        'checked_at' => 'datetime',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(PlatformDomain::class, 'domain_id');
    }
}
