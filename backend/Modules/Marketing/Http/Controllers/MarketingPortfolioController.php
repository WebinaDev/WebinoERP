<?php

namespace Modules\Marketing\Http\Controllers;

use Modules\Marketing\Entities\MarketingPortfolioItem;
use Modules\Marketing\Http\Controllers\Concerns\HandlesMarketingCrud;

class MarketingPortfolioController extends Controller
{
    use HandlesMarketingCrud;

    protected function modelClass(): string
    {
        return MarketingPortfolioItem::class;
    }

    protected function validationRules(bool $creating): array
    {
        return [
            'seo' => 'nullable|array',
            'seo.title' => 'nullable|string|max:120',
            'seo.description' => 'nullable|string|max:320',
            'seo.og_image' => 'nullable|string|max:500',
            'seo.canonical' => 'nullable|string|max:500',
            'seo.noindex' => 'nullable|boolean',
            'slug' => ($creating ? 'required' : 'sometimes').'|string|max:255',
            'title' => ($creating ? 'required' : 'sometimes').'|string|max:255',
            'description' => 'nullable|string',
            'images' => 'nullable|array',
            'service_id' => 'nullable|exists:marketing_services,id',
            'industry_id' => 'nullable|exists:marketing_solution_industries,id',
            'client' => 'nullable|string|max:255',
            'published' => 'nullable|boolean',
            'published_at' => 'nullable|date',
            'category_id' => 'nullable|exists:marketing_portfolio_categories,id',
            'cover_url' => 'nullable|string|max:500',
            'result_metric' => 'nullable|string|max:255',
            'technologies' => 'nullable|array',
            'case_study' => 'nullable|string',
            'featured' => 'nullable|boolean',
            'images.*' => 'string|max:500',
            'technologies.*' => 'string|max:120',
            'challenge' => 'nullable|string',
            'solution' => 'nullable|string',
            'results' => 'nullable|string',
            'sort_order' => 'nullable|integer',
        ];
    }
}
