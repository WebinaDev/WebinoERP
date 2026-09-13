<?php

namespace Modules\Integrations\Entities;

use Illuminate\Database\Eloquent\Model;

class ModirPayamakPatternRegistry extends Model
{
    protected $table = 'modirpayamak_pattern_registry';

    protected $fillable = [
        'domain',
        'scope',
        'event_key',
        'ippanel_code',
        'param_map',
        'sync_status',
        'last_error',
        'submitted_at',
    ];

    protected $casts = [
        'param_map' => 'array',
        'submitted_at' => 'datetime',
    ];
}
