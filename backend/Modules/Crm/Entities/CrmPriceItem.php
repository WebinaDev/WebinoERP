<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmPriceItem extends Model
{
    protected $table = 'crm_price_items';

    protected $fillable = [
        'price_book_id', 'product_id', 'min_qty', 'unit_price', 'discount_percent',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'discount_percent' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(CrmCatalogProduct::class, 'product_id');
    }
}
