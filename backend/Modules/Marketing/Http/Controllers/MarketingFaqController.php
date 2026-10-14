<?php

namespace Modules\Marketing\Http\Controllers;

use Modules\Marketing\Entities\MarketingFaqItem;
use Modules\Marketing\Http\Controllers\Concerns\HandlesMarketingCrud;

class MarketingFaqController extends Controller
{
    use HandlesMarketingCrud;

    protected function modelClass(): string
    {
        return MarketingFaqItem::class;
    }

    protected function validationRules(bool $creating): array
    {
        return [
            'group' => 'nullable|string|max:128',
            'question' => ($creating ? 'required' : 'sometimes').'|string|max:500',
            'answer' => ($creating ? 'required' : 'sometimes').'|string',
            'sort_order' => 'nullable|integer|min:0',
            'published' => 'nullable|boolean',
        ];
    }
}
