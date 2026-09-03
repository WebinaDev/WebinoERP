<?php

namespace Modules\Marketing\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MarketingTestimonial extends Model
{
    protected $table = 'marketing_testimonials';

    protected $fillable = ['author', 'role', 'company', 'quote', 'rating', 'avatar_url', 'published', 'sort_order'];

    protected $casts = ['published' => 'boolean'];

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('published', true);
    }
}
