<?php

namespace Modules\Marketplace\Entities;

use Illuminate\Database\Eloquent\Model;

class MarketplaceBasalamConnection extends Model
{
    protected $table = 'marketplace_basalam_connections';

    protected $fillable = [
        'site_url',
        'vendor_id',
        'status',
        'tokens',
        'connected_at',
        'last_seen_at',
        'disconnected_at',
    ];

    protected $hidden = ['tokens'];

    protected function casts(): array
    {
        return [
            'tokens' => 'encrypted:array',
            'vendor_id' => 'integer',
            'connected_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'disconnected_at' => 'datetime',
        ];
    }
}
