<?php

namespace Modules\Marketing\Http\Controllers;

use Modules\Marketing\Entities\MarketingDownload;
use Modules\Marketing\Http\Controllers\Concerns\HandlesMarketingCrud;

class MarketingDownloadsController extends Controller
{
    use HandlesMarketingCrud;

    protected function modelClass(): string
    {
        return MarketingDownload::class;
    }

    protected function validationRules(bool $creating): array
    {
        return [
            'title' => ($creating ? 'required' : 'sometimes').'|string|max:255',
            'file_id' => 'nullable|exists:marketing_media,id',
            'category' => 'nullable|string|max:128',
            'published' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
            'slug' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'file_type' => 'nullable|string|max:64',
            'file_url' => 'nullable|string|max:500',
            'gated' => 'nullable|boolean',
            'related_url' => 'nullable|string|max:500',
        ];
    }
}
