<?php

namespace Modules\Marketing\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
