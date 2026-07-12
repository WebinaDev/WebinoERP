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

class MarketingFaqController extends Controller
{
    use HandlesMarketingCrud;

    protected function modelClass(): string
    {
        return \Modules\Marketing\Entities\MarketingFaqItem::class;
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
