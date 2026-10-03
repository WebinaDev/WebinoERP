<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmPriceBook extends Model
{
    protected $table = 'crm_price_books';

    protected $fillable = [
        'company_id', 'name', 'currency_code', 'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(CrmPriceItem::class, 'price_book_id');
    }
}
