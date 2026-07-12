<?php

namespace Modules\Core\Entities;

use Illuminate\Database\Eloquent\Model;

/**
 * Parity helper for webinocrm cron hooks: tracks runs of scheduled commands.
 */
class CronRun extends Model
{
    protected $table = 'core_cron_runs';

    public $timestamps = false;

    protected $fillable = [
        'job',
        'status',
        'duration_ms',
        'summary',
        'error',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'summary' => 'array',
        'duration_ms' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}
