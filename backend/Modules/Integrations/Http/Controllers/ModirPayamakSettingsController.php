<?php

namespace Modules\Integrations\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Integrations\Entities\IntegrationSetting;

class ModirPayamakSettingsController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'data' => [
                'enabled' => IntegrationSetting::getString('modirpayamak', 'enabled', '0') === '1',
                'default_from' => IntegrationSetting::getString('modirpayamak', 'default_from', ''),
                'has_api_key' => IntegrationSetting::getString('modirpayamak', 'api_key', '') !== '',
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enabled' => 'nullable|boolean',
            'api_key' => 'nullable|string',
            'default_from' => 'nullable|string|max:30',
        ]);
        if (array_key_exists('enabled', $data)) {
            IntegrationSetting::putString('modirpayamak', 'enabled', $data['enabled'] ? '1' : '0');
        }
        if (! empty($data['api_key'])) {
            IntegrationSetting::putString('modirpayamak', 'api_key', $data['api_key']);
        }
        if (array_key_exists('default_from', $data)) {
            IntegrationSetting::putString('modirpayamak', 'default_from', $data['default_from'] ?? '');
        }

        return response()->json(['message' => 'Settings saved']);
    }
}
