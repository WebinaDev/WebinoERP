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

class MarketingPortfolioController extends Controller
{
    use HandlesMarketingCrud;

    protected function modelClass(): string
    {
        return \Modules\Marketing\Entities\MarketingPortfolioItem::class;
    }

    protected function validationRules(bool $creating): array
    {
        return [
            'slug' => ($creating ? 'required' : 'sometimes').'|string|max:255',
            'title' => ($creating ? 'required' : 'sometimes').'|string|max:255',
            'description' => 'nullable|string',
            'images' => 'nullable|array',
            'service_id' => 'nullable|exists:marketing_services,id',
            'industry_id' => 'nullable|exists:marketing_solution_industries,id',
            'client' => 'nullable|string|max:255',
            'published' => 'nullable|boolean',
            'published_at' => 'nullable|date',
        ];
    }
}
