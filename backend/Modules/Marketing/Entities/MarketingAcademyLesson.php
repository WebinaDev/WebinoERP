<?php

namespace Modules\Marketing\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingAcademyLesson extends Model
{
    protected $table = 'marketing_academy_lessons';

    protected $fillable = ['seo', 'course_id', 'chapter_id', 'summary', 'slug', 'title', 'content', 'video_url', 'sort_order', 'published'];

    protected $casts = ['seo' => 'array', 'published' => 'boolean', 'quiz' => 'array', 'is_preview' => 'boolean', 'duration_minutes' => 'integer'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(MarketingAcademyCourse::class, 'course_id');
    }
}
