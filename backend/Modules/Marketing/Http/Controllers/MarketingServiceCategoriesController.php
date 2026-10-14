<?php

namespace Modules\Marketing\Http\Controllers;

use Modules\Marketing\Entities\MarketingServiceCategory;
use Modules\Marketing\Http\Controllers\Concerns\HandlesMarketingCrud;

class MarketingServiceCategoriesController extends Controller
{
    use HandlesMarketingCrud;

    protected function modelClass(): string
    {
        return MarketingServiceCategory::class;
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
