<?php

namespace Modules\Sales\Entities;

use Illuminate\Database\Eloquent\Model;

class SalesCoupon extends Model
{
    protected $table = 'sales_coupons';

    protected $fillable = ['code', 'type', 'value', 'min_total', 'usage_limit', 'used', 'expires_at', 'active', 'show_in_cart', 'description'];

    protected $casts = ['value' => 'decimal:2', 'min_total' => 'decimal:2', 'expires_at' => 'datetime', 'active' => 'boolean', 'show_in_cart' => 'boolean'];
}
