<?php

namespace Modules\Marketing\Http\Controllers;

use Modules\Marketing\Entities\MarketingAcademyLesson;
use Modules\Marketing\Http\Controllers\Concerns\HandlesMarketingCrud;

/** Academy course syllabus (lessons), managed in داشبورد ← بازاریابی ← سرفصل‌های آکادمی. */
class MarketingAcademyLessonsController extends Controller
{
    use HandlesMarketingCrud;

    protected function modelClass(): string
    {
        return MarketingAcademyLesson::class;
    }

    protected function validationRules(bool $creating): array
    {
        return [
            'seo' => 'nullable|array',
            'seo.title' => 'nullable|string|max:120',
            'seo.description' => 'nullable|string|max:320',
            'seo.og_image' => 'nullable|string|max:500',
            'seo.canonical' => 'nullable|string|max:500',
            'seo.noindex' => 'nullable|boolean',
            'course_id' => ($creating ? 'required' : 'sometimes').'|exists:marketing_academy_courses,id',
            'slug' => ($creating ? 'required' : 'sometimes').'|string|max:255',
            'title' => ($creating ? 'required' : 'sometimes').'|string|max:255',
            'chapter_id' => 'nullable|exists:marketing_academy_chapters,id',
            'summary' => 'nullable|string',
            'content' => 'nullable|string',
            'video_url' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer|min:0',
            'published' => 'nullable|boolean',
        ];
    }
}
