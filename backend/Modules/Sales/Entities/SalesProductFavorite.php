<?php

namespace Modules\Sales\Entities;

use Illuminate\Database\Eloquent\Model;

class SalesProductFavorite extends Model
{
    protected $table = 'sales_product_favorites';

    protected $fillable = ['user_id', 'product_id'];

    protected $casts = [];

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SalesCatalogItem::class, 'product_id');
    }
}
