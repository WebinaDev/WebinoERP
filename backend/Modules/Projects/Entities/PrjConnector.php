<?php

namespace Modules\Projects\Entities;

use Illuminate\Database\Eloquent\Model;

class PrjConnector extends Model
{
    protected $table = 'prj_connectors';

    protected $fillable = [
        'kind', 'user_id', 'status', 'label', 'config', 'last_checked_at', 'last_message',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'last_checked_at' => 'datetime',
        ];
    }
}
