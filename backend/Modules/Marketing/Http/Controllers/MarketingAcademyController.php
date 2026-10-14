<?php

namespace Modules\Marketing\Http\Controllers;

use Modules\Marketing\Entities\MarketingAcademyCourse;
use Modules\Marketing\Http\Controllers\Concerns\HandlesMarketingCrud;

class MarketingAcademyController extends Controller
{
    use HandlesMarketingCrud;

    protected function modelClass(): string
    {
        return MarketingAcademyCourse::class;
    }

    protected function validationRules(bool $creating): array
    {
        return [
            'slug' => ($creating ? 'required' : 'sometimes').'|string|max:255',
            'title' => ($creating ? 'required' : 'sometimes').'|string|max:255',
            'description' => 'nullable|string',
            'cover_url' => 'nullable|string|max:500',
            'published' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ];
    }
}
