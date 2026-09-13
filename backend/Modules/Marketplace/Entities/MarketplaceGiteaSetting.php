<?php

namespace Modules\Marketplace\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Platform\Entities\PlatformSource;

class MarketplaceGiteaSetting extends Model
{
    protected $table = 'marketplace_gitea_settings';

    protected $fillable = [
        'provider',
        'host',
        'base_url',
        'org',
        'ip_override',
        'ip_scheme',
        'token',
        'platform_source_id',
    ];

    protected $hidden = ['token'];

    public function platformSource(): BelongsTo
    {
        return $this->belongsTo(PlatformSource::class, 'platform_source_id');
    }
}
