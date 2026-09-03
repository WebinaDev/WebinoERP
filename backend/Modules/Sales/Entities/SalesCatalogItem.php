<?php

namespace Modules\Sales\Entities;

use Illuminate\Database\Eloquent\Model;

class SalesCatalogItem extends Model
{
    protected $table = 'sales_catalog_items';

    protected $fillable = ['name', 'sku', 'price', 'description', 'status', 'type', 'meta'];

    protected $casts = [
        'price' => 'decimal:2',
        'meta' => 'array',
    ];
}
