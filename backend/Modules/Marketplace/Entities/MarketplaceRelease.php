<?php

namespace Modules\Marketplace\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceRelease extends Model
{
    protected $table = 'marketplace_releases';

    protected $fillable = ['module_id', 'version', 'changelog', 'status', 'published_at'];

    protected $casts = ['published_at' => 'datetime'];

    public function module(): BelongsTo
    {
        return $this->belongsTo(MarketplaceModule::class, 'module_id');
    }
}
