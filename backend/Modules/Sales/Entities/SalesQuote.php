<?php

namespace Modules\Sales\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesQuote extends Model
{
    protected $table = 'sales_quotes';

    protected $fillable = [
        'quote_number', 'customer_id', 'customer_name', 'crm_deal_id',
        'issue_date', 'valid_until', 'subtotal', 'discount_amount',
        'tax_amount', 'total_amount', 'status', 'terms_and_conditions', 'created_by',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'issue_date' => 'date',
        'valid_until' => 'date',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(SalesQuoteLine::class, 'quote_id');
    }
}
