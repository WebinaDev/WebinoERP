<?php

namespace Modules\Marketing\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingBlogPost extends Model
{
    protected $table = 'marketing_blog_posts';

    protected $fillable = [
        'category_id', 'slug', 'title', 'excerpt', 'body',
        'cover_url', 'status', 'published_at', 'wp_id', 'meta',
    ];

    protected $casts = ['published_at' => 'datetime', 'meta' => 'array'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(MarketingBlogCategory::class, 'category_id');
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published')->whereNotNull('published_at');
    }
}
