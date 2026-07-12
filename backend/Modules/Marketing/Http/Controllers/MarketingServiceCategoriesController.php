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

class MarketingServiceCategoriesController extends Controller
{
    use HandlesMarketingCrud;

    protected function modelClass(): string
    {
        return \Modules\Marketing\Entities\MarketingServiceCategory::class;
    }

    protected function validationRules(bool $creating): array
    {
        return [
            'parent_id' => 'nullable|exists:marketing_service_categories,id',
            'slug' => ($creating ? 'required' : 'sometimes').'|string|max:255',
            'name' => ($creating ? 'required' : 'sometimes').'|string|max:255',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
        ];
    }
}
