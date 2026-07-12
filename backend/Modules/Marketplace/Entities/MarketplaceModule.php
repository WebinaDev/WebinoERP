<?php

namespace Modules\Marketplace\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MarketplaceModule extends Model
{
    protected $table = 'marketplace_modules';

    protected $fillable = ['name', 'slug', 'description', 'status', 'category_id', 'price'];

    protected $casts = ['price' => 'decimal:2'];

    public function repo(): HasOne
    {
        return $this->hasOne(MarketplaceModuleRepo::class, 'module_id');
    }

    public function releases(): HasMany
    {
        return $this->hasMany(MarketplaceRelease::class, 'module_id');
    }
}
