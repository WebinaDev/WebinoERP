<?php

namespace Modules\Sales\Entities;

use Illuminate\Database\Eloquent\Model;

class SalesCart extends Model
{
    protected $table = 'sales_carts';

    protected $fillable = ['user_id', 'coupon_code'];

    protected $casts = [];

    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SalesCartItem::class, 'cart_id');
    }
}
