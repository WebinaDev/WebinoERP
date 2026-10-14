<?php

namespace Modules\Marketing\Http\Controllers;

use Modules\Marketing\Entities\MarketingBlogCategory;
use Modules\Marketing\Http\Controllers\Concerns\HandlesMarketingCrud;

class MarketingBlogCategoriesController extends Controller
{
    use HandlesMarketingCrud;

    protected function modelClass(): string
    {
        return MarketingBlogCategory::class;
    }

    protected function validationRules(bool $creating): array
    {
        return [
            'slug' => ($creating ? 'required' : 'sometimes').'|string|max:255',
            'name' => ($creating ? 'required' : 'sometimes').'|string|max:255',
        ];
    }
}
