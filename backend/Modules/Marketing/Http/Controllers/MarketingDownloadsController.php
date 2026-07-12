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

class MarketingDownloadsController extends Controller
{
    use HandlesMarketingCrud;

    protected function modelClass(): string
    {
        return \Modules\Marketing\Entities\MarketingDownload::class;
    }

    protected function validationRules(bool $creating): array
    {
        return [
            'title' => ($creating ? 'required' : 'sometimes').'|string|max:255',
            'file_id' => 'nullable|exists:marketing_media,id',
            'category' => 'nullable|string|max:128',
            'published' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ];
    }
}
