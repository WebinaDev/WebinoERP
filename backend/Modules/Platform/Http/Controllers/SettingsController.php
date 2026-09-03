<?php

namespace Modules\Platform\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Platform\Entities\PlatformSetting;

class SettingsController extends Controller
{
    public function show(): JsonResponse
    {
        $rows = PlatformSetting::query()->pluck('value', 'key');
        return response()->json(['success' => true, 'data' => [
            'default_proxy' => $rows['default_proxy'] ?? 'caddy',
            'wildcard_domain' => $rows['wildcard_domain'] ?? null,
            'api_enabled' => ($rows['api_enabled'] ?? '1') === '1',
        ], 'message' => null, 'meta' => null, 'errors' => null]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'default_proxy' => 'nullable|string|max:32',
            'wildcard_domain' => 'nullable|string|max:255',
            'api_enabled' => 'nullable|boolean',
        ]);
        foreach ($data as $k => $v) {
            if ($v === null) continue;
            PlatformSetting::query()->updateOrCreate(['key' => $k], ['value' => is_bool($v) ? ($v ? '1' : '0') : (string) $v]);
        }
        return $this->show();
    }
}
