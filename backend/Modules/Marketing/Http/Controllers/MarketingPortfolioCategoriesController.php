<?php

namespace Modules\Marketing\Http\Controllers;

use Modules\Marketing\Entities\MarketingPortfolioCategory;
use Modules\Marketing\Http\Controllers\Concerns\HandlesMarketingCrud;

class MarketingPortfolioCategoriesController extends Controller
{
    use HandlesMarketingCrud;

    protected function modelClass(): string
    {
        return MarketingPortfolioCategory::class;
    }

    protected function validationRules(bool $creating): array
    {
        return [
            'slug' => ($creating ? 'required' : 'sometimes').'|string|max:120',
            'name' => ($creating ? 'required' : 'sometimes').'|string|max:255',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
        ];
    }
}
