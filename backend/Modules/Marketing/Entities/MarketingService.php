<?php

namespace Modules\Marketing\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingService extends Model
{
    protected $table = 'marketing_services';

    protected $fillable = ['category_id', 'slug', 'title', 'excerpt', 'body', 'published', 'sort_order'];

    protected $casts = ['published' => 'boolean'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(MarketingServiceCategory::class, 'category_id');
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('published', true);
    }
}
