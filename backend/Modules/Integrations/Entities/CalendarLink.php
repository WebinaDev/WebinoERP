<?php

namespace Modules\Integrations\Entities;

use Illuminate\Database\Eloquent\Model;

class CalendarLink extends Model
{
    protected $table = 'int_calendar_links';

    protected $fillable = [
        'account_id', 'external_id', 'local_type', 'local_id', 'etag', 'synced_at', 'payload',
    ];

    protected function casts(): array
    {
        return [
            'synced_at' => 'datetime',
            'payload' => 'array',
        ];
    }
}
