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

class MarketingTestimonialsController extends Controller
{
    use HandlesMarketingCrud;

    protected function modelClass(): string
    {
        return \Modules\Marketing\Entities\MarketingTestimonial::class;
    }

    protected function validationRules(bool $creating): array
    {
        return [
            'author' => ($creating ? 'required' : 'sometimes').'|string|max:255',
            'role' => 'nullable|string|max:255',
            'company' => 'nullable|string|max:255',
            'quote' => ($creating ? 'required' : 'sometimes').'|string',
            'rating' => 'nullable|integer|min:1|max:5',
            'avatar_url' => 'nullable|string|max:500',
            'published' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ];
    }
}
