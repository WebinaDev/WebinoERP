<?php

namespace Modules\Integrations\Entities;

use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    protected $table = 'int_chat_messages';

    protected $fillable = [
        'bridge_id', 'direction', 'external_id', 'chat_id', 'body', 'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }
}
