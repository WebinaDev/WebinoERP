<?php

namespace Modules\Integrations\Entities;

use Illuminate\Database\Eloquent\Model;

class SocialConnector extends Model
{
    protected $table = 'int_social_connectors';

    protected $fillable = [
        'network', 'name', 'access_token', 'external_id', 'enabled', 'status', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'enabled' => 'boolean',
            'meta' => 'array',
        ];
    }
}
