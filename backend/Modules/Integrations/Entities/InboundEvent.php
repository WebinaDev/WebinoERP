<?php

namespace Modules\Integrations\Entities;

use Illuminate\Database\Eloquent\Model;

class InboundEvent extends Model
{
    protected $table = 'int_inbound_events';

    protected $fillable = [
        'provider', 'bridge_id', 'external_id', 'payload', 'status', 'attempts', 'last_error', 'available_at', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'available_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
