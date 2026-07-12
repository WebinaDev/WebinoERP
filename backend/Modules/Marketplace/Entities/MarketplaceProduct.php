<?php

namespace Modules\Marketplace\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceProduct extends Model
{
    protected $table = 'marketplace_products';

    protected $fillable = ['name', 'slug', 'price', 'description', 'category_id', 'status'];

    protected $casts = ['price' => 'decimal:2'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCategory::class, 'category_id');
    }
}
