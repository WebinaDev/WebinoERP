<?php

namespace Modules\Integrations\Entities;

use Illuminate\Database\Eloquent\Model;

class BaleEvent extends Model
{
    protected $fillable = ['chat_id', 'event_type', 'payload'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
