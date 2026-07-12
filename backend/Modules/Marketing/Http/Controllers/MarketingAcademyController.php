<?php

namespace Modules\Marketing\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\Marketing\Entities\MarketingMedia;
use Modules\Marketing\Entities\MarketingMediaFolder;
use Modules\Marketing\Entities\MarketingPage;
use Modules\Marketing\Entities\MarketingSiteSetting;
use Modules\Marketing\Http\Controllers\Concerns\HandlesMarketingCrud;

class MarketingAcademyController extends Controller
{
    use HandlesMarketingCrud;

    protected function modelClass(): string
    {
        return \Modules\Marketing\Entities\MarketingAcademyCourse::class;
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
