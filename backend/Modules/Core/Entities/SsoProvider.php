<?php

namespace Modules\Core\Entities;

use Illuminate\Database\Eloquent\Model;

class SsoProvider extends Model
{
    protected $table = 'core_sso_providers';

    protected $fillable = [
        'name', 'protocol', 'enabled', 'client_id', 'client_secret', 'issuer', 'metadata_url', 'redirect_uri', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'client_secret' => 'encrypted',
            'meta' => 'array',
        ];
    }
}
