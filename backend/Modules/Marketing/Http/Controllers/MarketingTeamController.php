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

class MarketingTeamController extends Controller
{
    use HandlesMarketingCrud;

    protected function modelClass(): string
    {
        return \Modules\Marketing\Entities\MarketingTeamMember::class;
    }

    protected function validationRules(bool $creating): array
    {
        return [
            'name' => ($creating ? 'required' : 'sometimes').'|string|max:255',
            'role' => 'nullable|string|max:255',
            'bio' => 'nullable|string',
            'photo_url' => 'nullable|string|max:500',
            'social_links' => 'nullable|array',
            'sort_order' => 'nullable|integer|min:0',
            'published' => 'nullable|boolean',
        ];
    }
}
