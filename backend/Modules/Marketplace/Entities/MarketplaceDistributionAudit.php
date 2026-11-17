<?php

namespace Modules\Marketplace\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceDistributionAudit extends Model
{
    protected $table = 'marketplace_distribution_audits';

    protected $fillable = [
        'release_id',
        'module_id',
        'user_id',
        'action',
        'checksum_sha256',
        'ip_address',
        'user_agent',
        'validation_details',
    ];

    protected $casts = [
        'validation_details' => 'array',
    ];

    public function release(): BelongsTo
    {
        return $this->belongsTo(MarketplaceRelease::class, 'release_id');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(MarketplaceModule::class, 'module_id');
    }
}
