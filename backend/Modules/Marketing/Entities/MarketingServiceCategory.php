<?php

namespace Modules\Marketing\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingServiceCategory extends Model
{
    protected $table = 'marketing_service_categories';

    protected $fillable = ['parent_id', 'slug', 'name', 'description', 'sort_order'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function services(): HasMany
    {
        return $this->hasMany(MarketingService::class, 'category_id')->orderBy('sort_order');
    }
}
