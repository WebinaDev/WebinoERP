<?php

namespace Modules\Sales\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesInvoiceLine extends Model
{
    protected $table = 'sales_invoice_lines';

    protected $fillable = [
        'invoice_id', 'product_id', 'item_name', 'quantity', 'uom',
        'unit_price', 'discount_rate', 'discount_amount', 'tax_rate',
        'tax_amount', 'line_total', 'notes',
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
