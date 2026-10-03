<?php

namespace Modules\Integrations\Entities;

use Illuminate\Database\Eloquent\Model;

class ChannelDelivery extends Model
{
    protected $table = 'int_channel_deliveries';

    protected $fillable = [
        'user_id', 'channel', 'event_key', 'priority', 'status', 'reason', 'body', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }
}
