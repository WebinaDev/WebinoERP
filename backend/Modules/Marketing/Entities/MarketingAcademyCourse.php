<?php

namespace Modules\Marketing\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingAcademyCourse extends Model
{
    protected $table = 'marketing_academy_courses';

    protected $fillable = ['seo', 'slug', 'title', 'description', 'cover_url', 'published', 'sort_order', 'level', 'duration', 'instructor', 'outcomes', 'enroll_url', 'is_free'];

    protected $casts = ['seo' => 'array', 'is_free' => 'boolean', 'published' => 'boolean'];

    public function chapters(): HasMany
    {
        return $this->hasMany(MarketingAcademyChapter::class, 'course_id')->orderBy('sort_order');
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(MarketingAcademyLesson::class, 'course_id')->orderBy('sort_order');
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('published', true);
    }
}
