<?php

namespace Modules\Marketing\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A course chapter (فصل / سرفصل) grouping lessons. */
class MarketingAcademyChapter extends Model
{
    protected $table = 'marketing_academy_chapters';

    protected $fillable = ['course_id', 'title', 'summary', 'sort_order'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(MarketingAcademyCourse::class, 'course_id');
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(MarketingAcademyLesson::class, 'chapter_id')->orderBy('sort_order');
    }
}
