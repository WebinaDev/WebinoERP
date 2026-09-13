<?php

namespace Modules\Marketplace\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceOrderItem extends Model
{
    protected $table = 'marketplace_order_items';

    protected $fillable = [
        'order_id',
        'module_id',
        'module_slug',
        'release_id',
        'unit_price',
        'quantity',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrder::class, 'order_id');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(MarketplaceModule::class, 'module_id');
    }
}
