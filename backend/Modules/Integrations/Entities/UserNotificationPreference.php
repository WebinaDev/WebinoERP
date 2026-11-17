<?php

namespace Modules\Integrations\Entities;

use Illuminate\Database\Eloquent\Model;

class UserNotificationPreference extends Model
{
    protected $table = 'user_notification_preferences';

    protected $fillable = [
        'user_id', 'event_key', 'channel_in_app', 'channel_sms',
        'channel_email', 'channel_chat', 'quiet_hours_start', 'quiet_hours_end',
    ];

    protected $casts = [
        'channel_in_app' => 'boolean',
        'channel_sms' => 'boolean',
        'channel_email' => 'boolean',
        'channel_chat' => 'boolean',
    ];
}
