<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Modules\Core\Entities\SystemSetting;

class SystemConfigController extends Controller
{
    /**
     * Get system configuration for frontend
     */
    public function getConfig(): JsonResponse
    {
        $branding = SystemSetting::getByGroup('branding');
        
        return response()->json([
            'data' => [
                'theme' => [
                    'primary' => $branding['primary_color'] ?? '#0f172a',
                    'radius' => $branding['border_radius'] ?? '0.5rem',
                ],
                'branding' => [
                    'name' => $branding['brand_name'] ?? config('app.name'),
                    'logo' => $branding['brand_logo_url'] ?? null,
                ],
            ],
        ]);
    }
}

