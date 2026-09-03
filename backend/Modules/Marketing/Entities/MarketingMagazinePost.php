<?php

namespace Modules\Marketing\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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
