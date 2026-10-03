<?php

namespace Modules\Integrations\Entities;

use Illuminate\Database\Eloquent\Model;

class CalendarAccount extends Model
{
    protected $table = 'int_calendar_accounts';

    protected $fillable = [
        'user_id', 'provider', 'email', 'access_token', 'refresh_token', 'expires_at',
        'calendar_id', 'sync_token', 'webhook_channel_id', 'webhook_secret', 'webhook_expires_at',
        'status', 'last_synced_at', 'meta', 'refresh_attempts', 'last_refresh_error',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'expires_at' => 'datetime',
            'webhook_expires_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'meta' => 'array',
        ];
    }
}
