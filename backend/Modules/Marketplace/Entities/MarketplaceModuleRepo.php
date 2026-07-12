<?php

namespace Modules\Marketplace\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceModuleRepo extends Model
{
    protected $table = 'marketplace_module_repos';

    protected $fillable = ['module_id', 'repo_url', 'repo_branch', 'gitea_repo', 'last_synced_at'];

    protected $casts = ['last_synced_at' => 'datetime'];

    public function module(): BelongsTo
    {
        return $this->belongsTo(MarketplaceModule::class, 'module_id');
    }
}
