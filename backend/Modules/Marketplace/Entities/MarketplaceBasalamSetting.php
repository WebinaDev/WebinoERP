<?php

namespace Modules\Marketplace\Entities;

use Illuminate\Database\Eloquent\Model;

class MarketplaceBasalamSetting extends Model
{
    protected $table = 'marketplace_basalam_settings';

    protected $fillable = [
        'client_id',
        'client_secret',
        'redirect_uri',
        'scopes',
    ];

    protected $hidden = ['client_secret'];

    protected function casts(): array
    {
        return [
            'client_secret' => 'encrypted',
        ];
    }
}
