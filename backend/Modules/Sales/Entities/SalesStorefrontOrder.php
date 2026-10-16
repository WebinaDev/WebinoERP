<?php

namespace Modules\Sales\Entities;

use Illuminate\Database\Eloquent\Model;

class SalesStorefrontOrder extends Model
{
    protected $table = 'sales_storefront_orders';

    protected $fillable = ['number', 'user_id', 'status', 'payment_method', 'items', 'address', 'phone', 'subtotal', 'discount', 'shipping', 'total', 'coupon_code', 'tracking_code', 'invoice_id', 'customer_name', 'customer_email', 'guest_token', 'payment_mode', 'paid_at'];

    protected $casts = ['items' => 'array', 'address' => 'array', 'subtotal' => 'decimal:2', 'discount' => 'decimal:2', 'shipping' => 'decimal:2', 'total' => 'decimal:2', 'paid_at' => 'datetime'];
}
