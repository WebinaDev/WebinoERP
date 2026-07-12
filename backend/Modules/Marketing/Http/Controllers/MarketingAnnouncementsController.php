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

class MarketingAnnouncementsController extends Controller
{
    use HandlesMarketingCrud;

    protected function modelClass(): string
    {
        return \Modules\Marketing\Entities\MarketingAnnouncement::class;
    }

    protected function validationRules(bool $creating): array
    {
        return [
            'title' => ($creating ? 'required' : 'sometimes').'|string|max:255',
            'body' => 'nullable|string',
            'pinned' => 'nullable|boolean',
            'published' => 'nullable|boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date',
        ];
    }
}
