<?php

namespace Modules\Marketplace\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketplaceCategory extends Model
{
    protected $table = 'marketplace_categories';

    protected $fillable = ['name', 'slug', 'sort', 'status'];

    protected $casts = [
        'sort' => 'integer',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(MarketplaceProduct::class, 'category_id');
    }

    public function modules(): HasMany
    {
        return $this->hasMany(MarketplaceModule::class, 'category_id');
    }
}
