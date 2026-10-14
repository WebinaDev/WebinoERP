<?php

namespace Modules\Marketing\Http\Controllers;

use Modules\Marketing\Entities\MarketingTeamMember;
use Modules\Marketing\Http\Controllers\Concerns\HandlesMarketingCrud;

class MarketingTeamController extends Controller
{
    use HandlesMarketingCrud;

    protected function modelClass(): string
    {
        return MarketingTeamMember::class;
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
