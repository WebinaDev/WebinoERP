<?php

namespace Modules\Marketing\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingMenuItem extends Model
{
    protected $fillable = [
        'menu_id', 'parent_id', 'label', 'label_en', 'href', 'sort_order', 'published',
    ];

    protected $casts = ['published' => 'boolean'];

    public function menu(): BelongsTo
    {
        return $this->belongsTo(MarketingMenu::class, 'menu_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }
}
