<?php

namespace Modules\Sales\Entities;

use Illuminate\Database\Eloquent\Model;

class SalesCatalogItem extends Model
{
    protected $table = 'sales_catalog_items';

    protected $fillable = ['name', 'sku', 'price', 'description', 'status'];

    protected $casts = ['price' => 'decimal:2'];
}
