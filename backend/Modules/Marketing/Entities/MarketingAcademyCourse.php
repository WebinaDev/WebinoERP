<?php

namespace Modules\Marketing\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingAcademyCourse extends Model
{
    protected $table = 'marketing_academy_courses';

    protected $fillable = ['slug', 'title', 'description', 'cover_url', 'published', 'sort_order'];

    protected $casts = ['published' => 'boolean'];

    public function lessons(): HasMany
    {
        return $this->hasMany(MarketingAcademyLesson::class, 'course_id')->orderBy('sort_order');
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('published', true);
    }
}
