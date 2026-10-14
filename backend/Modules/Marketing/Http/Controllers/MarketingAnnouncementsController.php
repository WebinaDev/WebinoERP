<?php

namespace Modules\Marketing\Http\Controllers;

use Modules\Marketing\Entities\MarketingAnnouncement;
use Modules\Marketing\Http\Controllers\Concerns\HandlesMarketingCrud;

class MarketingAnnouncementsController extends Controller
{
    use HandlesMarketingCrud;

    protected function modelClass(): string
    {
        return MarketingAnnouncement::class;
    }

    protected function validationRules(bool $creating): array
    {
        return [
            'seo' => 'nullable|array',
            'seo.title' => 'nullable|string|max:120',
            'seo.description' => 'nullable|string|max:320',
            'seo.og_image' => 'nullable|string|max:500',
            'seo.canonical' => 'nullable|string|max:500',
            'seo.noindex' => 'nullable|boolean',
            'title' => ($creating ? 'required' : 'sometimes').'|string|max:255',
            'body' => 'nullable|string',
            'pinned' => 'nullable|boolean',
            'published' => 'nullable|boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date',
            'slug' => 'nullable|string|max:255',
            'type' => 'nullable|string|max:32',
            'summary' => 'nullable|string',
        ];
    }
}
