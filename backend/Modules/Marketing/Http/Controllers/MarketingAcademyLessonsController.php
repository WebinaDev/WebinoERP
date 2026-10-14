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
            'course_id' => ($creating ? 'required' : 'sometimes').'|exists:marketing_academy_courses,id',
            'slug' => ($creating ? 'required' : 'sometimes').'|string|max:255',
            'title' => ($creating ? 'required' : 'sometimes').'|string|max:255',
            'content' => 'nullable|string',
            'video_url' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer|min:0',
            'published' => 'nullable|boolean',
        ];
    }
}
