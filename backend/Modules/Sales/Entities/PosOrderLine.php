<?php

namespace Modules\Sales\Entities;

use Illuminate\Database\Eloquent\Model;

class PosOrderLine extends Model
{
    protected $table = 'pos_order_lines';

    protected $fillable = [
        'order_id', 'product_id', 'variant_id', 'refund_of_line_id', 'name', 'barcode', 'quantity', 'unit_price',
        'discount_amount', 'tax_rate', 'tax_amount', 'line_total', 'refunded_quantity', 'stock_tracked',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
            'refunded_quantity' => 'decimal:3',
            'stock_tracked' => 'boolean',
        ];
    }
}
