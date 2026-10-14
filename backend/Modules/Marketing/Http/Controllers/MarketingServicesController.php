<?php

namespace Modules\Marketing\Http\Controllers;

use Modules\Marketing\Entities\MarketingService;
use Modules\Marketing\Http\Controllers\Concerns\HandlesMarketingCrud;

class MarketingServicesController extends Controller
{
    use HandlesMarketingCrud;

    protected function modelClass(): string
    {
        return MarketingService::class;
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
