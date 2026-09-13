<?php

namespace Modules\Marketplace\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceEntitlement extends Model
{
    protected $table = 'marketplace_entitlements';

    protected $fillable = [
        'domain',
        'module_id',
        'status',
        'order_id',
        'installed_version',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'module_id' => 'integer',
        'order_id' => 'integer',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(MarketplaceModule::class, 'module_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrder::class, 'order_id');
    }
}
