<?php

namespace Modules\Marketing\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingPortfolioItem extends Model
{
    protected $table = 'marketing_portfolio_items';

    protected $fillable = [
        'slug', 'title', 'description', 'images', 'service_id',
        'industry_id', 'client', 'published', 'published_at',
        'category_id', 'cover_url', 'result_metric', 'technologies', 'case_study', 'featured',
    ];

    protected $casts = [
        'images' => 'array',
        'technologies' => 'array',
        'published' => 'boolean',
        'featured' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(MarketingPortfolioCategory::class, 'category_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(MarketingService::class, 'service_id');
    }

    public function industry(): BelongsTo
    {
        return $this->belongsTo(MarketingSolutionIndustry::class, 'industry_id');
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('published', true);
    }
}
