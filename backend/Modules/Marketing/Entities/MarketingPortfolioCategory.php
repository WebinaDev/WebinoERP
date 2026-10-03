<?php

namespace Modules\Marketing\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingPortfolioCategory extends Model
{
    protected $fillable = ['slug', 'name', 'description', 'sort_order'];

    public function items(): HasMany
    {
        return $this->hasMany(MarketingPortfolioItem::class, 'category_id');
    }
}
