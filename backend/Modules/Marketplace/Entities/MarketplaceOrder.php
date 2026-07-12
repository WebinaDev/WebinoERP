<?php

namespace Modules\Marketplace\Entities;

use Illuminate\Database\Eloquent\Model;

class MarketplaceOrder extends Model
{
    protected $table = 'marketplace_orders';

    protected $fillable = ['order_number', 'total', 'status', 'user_id'];

    protected $casts = ['total' => 'decimal:2'];
}
