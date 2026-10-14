<?php

namespace Modules\Marketing\Http\Controllers;

use Modules\Marketing\Entities\MarketingTestimonial;
use Modules\Marketing\Http\Controllers\Concerns\HandlesMarketingCrud;

class MarketingTestimonialsController extends Controller
{
    use HandlesMarketingCrud;

    protected function modelClass(): string
    {
        return MarketingTestimonial::class;
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
