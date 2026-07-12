<?php

namespace Modules\Marketplace\Entities;

use Illuminate\Database\Eloquent\Model;

class MarketplaceGiteaSetting extends Model
{
    protected $table = 'marketplace_gitea_settings';

    protected $fillable = ['host', 'org', 'token'];

    protected $hidden = ['token'];
}
