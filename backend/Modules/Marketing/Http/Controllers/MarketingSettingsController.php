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

class MarketingSettingsController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(['data' => MarketingSiteSetting::current()]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'logo_url' => 'nullable|string|max:500',
            'favicon_url' => 'nullable|string|max:500',
            'site_name' => 'nullable|string|max:255',
            'active_theme_slug' => 'nullable|string|max:64',
            'branding' => 'nullable|array',
            'nav' => 'nullable|array',
            'home_blocks' => 'nullable|array',
            'social_links' => 'nullable|array',
        ]);
        $settings = MarketingSiteSetting::current();
        $settings->update($data);

        return response()->json(['data' => $settings->fresh()]);
    }
}
