<?php

namespace Modules\Sales\Entities;

use Illuminate\Database\Eloquent\Model;

class SalesQuoteLine extends Model
{
    protected $table = 'sales_quote_lines';

    protected $fillable = [
        'quote_id', 'product_id', 'item_name', 'quantity', 'uom',
        'unit_price', 'discount_rate', 'discount_amount', 'tax_rate',
        'tax_amount', 'line_total',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'discount_rate' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];
}
