<?php

namespace Modules\Integrations\Entities;

use Illuminate\Database\Eloquent\Model;

class ModirPayamakMessage extends Model
{
    protected $table = 'modirpayamak_messages';

    protected $fillable = [
        'domain',
        'sending_type',
        'from_number',
        'pattern_code',
        'message',
        'recipients',
        'status',
        'cost',
        'edge_payload',
    ];

    protected $casts = [
        'recipients' => 'array',
        'edge_payload' => 'array',
        'cost' => 'decimal:2',
    ];
}
