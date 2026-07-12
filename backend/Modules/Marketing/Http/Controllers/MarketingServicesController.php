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

class MarketingServicesController extends Controller
{
    use HandlesMarketingCrud;

    protected function modelClass(): string
    {
        return \Modules\Marketing\Entities\MarketingService::class;
    }

    protected function validationRules(bool $creating): array
    {
        return [
            'category_id' => 'nullable|exists:marketing_service_categories,id',
            'slug' => ($creating ? 'required' : 'sometimes').'|string|max:255',
            'title' => ($creating ? 'required' : 'sometimes').'|string|max:255',
            'excerpt' => 'nullable|string',
            'body' => 'nullable|string',
            'published' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ];
    }
}
