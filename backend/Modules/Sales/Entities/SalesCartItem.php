<?php

namespace Modules\Sales\Entities;

use Illuminate\Database\Eloquent\Model;

class SalesCartItem extends Model
{
    protected $table = 'sales_cart_items';

    protected $fillable = ['cart_id', 'product_id', 'quantity', 'saved_for_later'];

    protected $casts = ['quantity' => 'integer', 'saved_for_later' => 'boolean'];

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SalesCatalogItem::class, 'product_id');
    }
}
