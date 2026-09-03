<?php

namespace Modules\Marketing\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingSolutionPage extends Model
{
    protected $table = 'marketing_solution_pages';

    protected $fillable = ['industry_id', 'slug', 'title', 'body', 'published'];

    protected $casts = ['published' => 'boolean'];

    public function industry(): BelongsTo
    {
        return $this->belongsTo(MarketingSolutionIndustry::class, 'industry_id');
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('published', true);
    }
}
