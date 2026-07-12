<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Core\Entities\SystemSetting;

class PwaManifestController extends Controller
{
    public function json(): JsonResponse
    {
        $name = SystemSetting::get('app_name', config('app.name', 'Webino'));
        $short = SystemSetting::get('app_short_name', $name);
        $theme = SystemSetting::get('pwa_theme_color', '#4CAF50');
        $bg = SystemSetting::get('pwa_background_color', '#ffffff');

        return response()->json([
            'name' => $name,
            'short_name' => $short,
            'start_url' => '/',
            'display' => 'standalone',
            'background_color' => $bg,
            'theme_color' => $theme,
            'icons' => [
                ['src' => '/favicon.ico', 'sizes' => '64x64', 'type' => 'image/x-icon'],
            ],
        ]);
    }
}
