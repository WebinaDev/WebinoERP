<?php

namespace Modules\Marketing\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class MarketingPage extends Model
{
    protected $table = 'marketing_pages';

    protected $fillable = [
        'slug', 'title_fa', 'title_en', 'body_fa', 'body_en',
        'template', 'published', 'wp_id', 'meta',
        'builder_draft', 'builder_published', 'status',
        'title', 'body', 'tenant_id', 'seo', 'builder_publish_at', 'builder_unpublish_at', 'lang', 'translation_group',
    ];

    protected $casts = [
        'published' => 'boolean',
        'meta' => 'array',
        'builder_draft' => 'array',
        'builder_published' => 'array',
        'seo' => 'array',
        'builder_publish_at' => 'datetime',
        'builder_unpublish_at' => 'datetime',
    ];

    /** Dashboard-compatible `title` (ported builder code) mapped onto `title_fa`. */
    protected function title(): Attribute
    {
        return Attribute::make(
            get: fn ($value, array $attributes) => $attributes['title_fa'] ?? null,
            set: fn ($value) => ['title_fa' => $value],
        );
    }

    /** Dashboard-compatible `body` mapped onto `body_fa`. */
    protected function body(): Attribute
    {
        return Attribute::make(
            get: fn ($value, array $attributes) => $attributes['body_fa'] ?? null,
            set: fn ($value) => ['body_fa' => $value],
        );
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('published', true);
    }
}
