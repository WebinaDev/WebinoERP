<?php

namespace Modules\Integrations\Entities;

use Illuminate\Database\Eloquent\Model;

class CommunicationEventCatalog extends Model
{
    protected $table = 'communication_event_catalog';

    protected $fillable = [
        'event_key', 'category', 'title_fa', 'title_en',
        'default_priority', 'allowed_channels', 'is_transactional', 'is_active',
    ];

    protected $casts = [
        'allowed_channels' => 'array',
        'is_transactional' => 'boolean',
        'is_active' => 'boolean',
    ];
}
