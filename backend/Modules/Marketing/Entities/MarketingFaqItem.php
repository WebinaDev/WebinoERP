<?php

namespace Modules\Marketing\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MarketingFaqItem extends Model
{
    protected $table = 'marketing_faq_items';

    protected $fillable = ['group', 'question', 'answer', 'sort_order', 'published'];

    protected $casts = ['published' => 'boolean'];

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('published', true);
    }
}
