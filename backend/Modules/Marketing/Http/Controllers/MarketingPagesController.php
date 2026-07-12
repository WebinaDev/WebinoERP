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

class MarketingPagesController extends Controller
{
    use HandlesMarketingCrud;

    protected function modelClass(): string
    {
        return MarketingPage::class;
    }

    protected function validationRules(bool $creating): array
    {
        return [
            'slug' => ($creating ? 'required' : 'sometimes').'|string|max:255',
            'title_fa' => ($creating ? 'required' : 'sometimes').'|string|max:255',
            'title_en' => 'nullable|string|max:255',
            'body_fa' => 'nullable|string',
            'body_en' => 'nullable|string',
            'template' => 'nullable|string|max:64',
            'published' => 'nullable|boolean',
            'meta' => 'nullable|array',
        ];
    }
}
