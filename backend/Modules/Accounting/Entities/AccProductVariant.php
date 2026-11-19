<?php

namespace Modules\Accounting\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccProductVariant extends Model
{
    protected $table = 'acc_product_variants';

    protected $fillable = ['product_id', 'name', 'sku', 'barcode', 'sell_price', 'attributes', 'is_active'];

    protected function casts(): array
    {
        return [
            'sell_price' => 'decimal:2',
            'attributes' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(AccProduct::class, 'product_id');
    }
}
