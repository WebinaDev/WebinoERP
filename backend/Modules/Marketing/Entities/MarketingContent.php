<?php

namespace Modules\Marketing\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingPage extends Model
{
    protected $table = 'marketing_pages';

    protected $fillable = [
        'slug', 'title_fa', 'title_en', 'body_fa', 'body_en',
        'template', 'published', 'wp_id', 'meta',
    ];

    protected $casts = ['published' => 'boolean', 'meta' => 'array'];

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('published', true);
    }
}

class MarketingBlogCategory extends Model
{
    protected $table = 'marketing_blog_categories';

    protected $fillable = ['slug', 'name'];

    public function posts(): HasMany
    {
        return $this->hasMany(MarketingBlogPost::class, 'category_id');
    }
}

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

class MarketingMagazinePost extends Model
{
    protected $table = 'marketing_magazine_posts';

    protected $fillable = [
        'slug', 'title', 'excerpt', 'body', 'cover_url',
        'status', 'published_at', 'wp_id', 'meta',
    ];

    protected $casts = ['published_at' => 'datetime', 'meta' => 'array'];

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published')->whereNotNull('published_at');
    }
}
