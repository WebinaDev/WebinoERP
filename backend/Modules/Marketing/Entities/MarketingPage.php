<?php

namespace Modules\Marketing\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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
