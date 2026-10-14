<?php

namespace Modules\Marketing\Http\Controllers;

use Modules\Marketing\Entities\MarketingAcademyChapter;
use Modules\Marketing\Http\Controllers\Concerns\HandlesMarketingCrud;

/** Course chapters (فصل‌ها); usually edited through the course-structure panel. */
class MarketingAcademyChaptersController extends Controller
{
    use HandlesMarketingCrud;

    protected function modelClass(): string
    {
        return MarketingAcademyChapter::class;
    }

    protected function validationRules(bool $creating): array
    {
        return [
            'course_id' => ($creating ? 'required' : 'sometimes').'|exists:marketing_academy_courses,id',
            'title' => ($creating ? 'required' : 'sometimes').'|string|max:255',
            'summary' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
        ];
    }
}
