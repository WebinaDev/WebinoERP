<?php

namespace Modules\Accounting\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccProduct extends Model
{
    protected $table = 'acc_products';

    protected $fillable = [
        'name', 'unit', 'barcode', 'category', 'buy_price', 'sell_price', 'inventory_controlled',
    ];

    protected function casts(): array
    {
        return [
            'buy_price' => 'decimal:2',
            'sell_price' => 'decimal:2',
            'inventory_controlled' => 'boolean',
        ];
    }

    public function variants(): HasMany
    {
        return $this->hasMany(AccProductVariant::class, 'product_id');
    }
}
