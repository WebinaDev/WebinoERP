<?php

namespace Modules\Marketing\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingMenu extends Model
{
    protected $fillable = ['location', 'name', 'published'];

    protected $casts = ['published' => 'boolean'];

    public function items(): HasMany
    {
        return $this->hasMany(MarketingMenuItem::class, 'menu_id')->orderBy('sort_order');
    }
}
