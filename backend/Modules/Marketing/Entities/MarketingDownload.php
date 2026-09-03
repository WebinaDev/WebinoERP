<?php

namespace Modules\Marketing\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingDownload extends Model
{
    protected $table = 'marketing_downloads';

    protected $fillable = ['title', 'file_id', 'category', 'published', 'sort_order'];

    protected $casts = ['published' => 'boolean'];

    public function file(): BelongsTo
    {
        return $this->belongsTo(MarketingMedia::class, 'file_id');
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('published', true);
    }
}
