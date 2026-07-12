<?php

namespace Modules\Marketing\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

class MarketingAcademyLesson extends Model
{
    protected $table = 'marketing_academy_lessons';

    protected $fillable = ['course_id', 'slug', 'title', 'content', 'video_url', 'sort_order', 'published'];

    protected $casts = ['published' => 'boolean'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(MarketingAcademyCourse::class, 'course_id');
    }
}

class MarketingPortfolioItem extends Model
{
    protected $table = 'marketing_portfolio_items';

    protected $fillable = [
        'slug', 'title', 'description', 'images', 'service_id',
        'industry_id', 'client', 'published', 'published_at',
    ];

    protected $casts = ['images' => 'array', 'published' => 'boolean', 'published_at' => 'datetime'];

    public function service(): BelongsTo
    {
        return $this->belongsTo(MarketingService::class, 'service_id');
    }

    public function industry(): BelongsTo
    {
        return $this->belongsTo(MarketingSolutionIndustry::class, 'industry_id');
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('published', true);
    }
}

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

class MarketingAnnouncement extends Model
{
    protected $table = 'marketing_announcements';

    protected $fillable = ['title', 'body', 'pinned', 'published', 'starts_at', 'ends_at'];

    protected $casts = [
        'pinned' => 'boolean',
        'published' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('published', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }
}

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

class MarketingTeamMember extends Model
{
    protected $table = 'marketing_team_members';

    protected $fillable = ['name', 'role', 'bio', 'photo_url', 'social_links', 'sort_order', 'published'];

    protected $casts = ['social_links' => 'array', 'published' => 'boolean'];

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('published', true);
    }
}
