<?php

namespace Modules\Marketing\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingBlogCategory extends Model
{
    protected $table = 'marketing_blog_categories';

    protected $fillable = ['slug', 'name'];

    public function posts(): HasMany
    {
        return $this->hasMany(MarketingBlogPost::class, 'category_id');
    }
}
