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
            'title' => ($creating ? 'required' : 'sometimes').'|string|max:255',
            'body' => 'nullable|string',
            'pinned' => 'nullable|boolean',
            'published' => 'nullable|boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date',
        ];
    }
}
