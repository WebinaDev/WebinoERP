<?php

namespace Modules\Marketing\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Marketing\Entities\MarketingSiteSetting;
use Modules\Marketing\Support\EnamadFile;

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
        if (isset($data['branding']) && is_array($data['branding']) && array_key_exists('enamad_file', $data['branding'])) {
            $data['branding']['enamad_file'] = EnamadFile::normalize($data['branding']['enamad_file']);
        }
        $settings = MarketingSiteSetting::current();
        $settings->update($data);

        return response()->json(['data' => $settings->fresh()]);
    }
}
