<?php

namespace Modules\Marketing\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingDownload extends Model
{
    protected $table = 'marketing_downloads';

    protected $fillable = ['title', 'file_id', 'category', 'published', 'sort_order', 'slug', 'description', 'file_type', 'file_url', 'gated', 'related_url'];

    protected $casts = ['gated' => 'boolean', 'published' => 'boolean'];

    public function file(): BelongsTo
    {
        return $this->belongsTo(MarketingMedia::class, 'file_id');
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('published', true);
    }
}
