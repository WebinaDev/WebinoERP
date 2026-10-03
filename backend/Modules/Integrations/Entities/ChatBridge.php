<?php

namespace Modules\Integrations\Entities;

use Illuminate\Database\Eloquent\Model;

class ChatBridge extends Model
{
    protected $table = 'int_chat_bridges';

    protected $fillable = [
        'provider', 'name', 'bot_token', 'webhook_secret', 'channel_id', 'inbound_commands', 'enabled',
    ];

    protected function casts(): array
    {
        return [
            'bot_token' => 'encrypted',
            'webhook_secret' => 'encrypted',
            'inbound_commands' => 'boolean',
            'enabled' => 'boolean',
        ];
    }
}
