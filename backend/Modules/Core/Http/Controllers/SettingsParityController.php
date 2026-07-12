<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Entities\SystemSetting;
use Modules\Integrations\Entities\IntegrationSetting;

class SettingsParityController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $general = SystemSetting::getByGroup('general');
        $modules = SystemSetting::getByGroup('modules');
        $branding = SystemSetting::getByGroup('branding');

        return response()->json([
            'data' => [
                'general' => $general,
                'modules' => $modules,
                'branding' => $branding,
                'auth' => SystemSetting::getByGroup('auth'),
                'sms' => $this->smsSettingsForUi(),
                'payment' => SystemSetting::getByGroup('payment'),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'settings' => 'required|array',
            'group' => 'nullable|string|max:50',
        ]);
        $group = $payload['group'] ?? 'general';
        foreach ($payload['settings'] as $key => $value) {
            SystemSetting::set((string) $key, is_scalar($value) ? (string) $value : json_encode($value), $group);
        }

        if ($group === 'sms') {
            $this->mirrorSmsToIntegration($payload['settings']);
        }

        return response()->json(['data' => ['saved' => true]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function smsSettingsForUi(): array
    {
        $fromSystem = SystemSetting::getByGroup('sms');
        $fromIntegration = IntegrationSetting::getJson('sms', 'settings', []);
        $provider = $fromIntegration['provider'] ?? $fromSystem['provider'] ?? config('integrations.sms.default', 'log');

        return array_merge($fromSystem, $fromIntegration, ['provider' => $provider]);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function mirrorSmsToIntegration(array $settings): void
    {
        $payload = [];
        foreach (['provider', 'api_key', 'sender', 'username', 'password', 'pattern_id'] as $key) {
            if (array_key_exists($key, $settings) && $settings[$key] !== '') {
                $payload[$key] = is_scalar($settings[$key]) ? (string) $settings[$key] : json_encode($settings[$key]);
            }
        }

        if ($payload === []) {
            return;
        }

        $current = IntegrationSetting::getJson('sms', 'settings', []);
        IntegrationSetting::putJson('sms', 'settings', array_merge($current, $payload));
    }

    public function updateWhiteLabel(Request $request): JsonResponse
    {
        $data = $request->validate([
            'app_name' => 'nullable|string|max:191',
            'logo_url' => 'nullable|string|max:500',
            'primary_color' => 'nullable|string|max:20',
        ]);
        foreach ($data as $k => $v) {
            if ($v !== null) {
                SystemSetting::set('wl_'.$k, $v, 'branding');
            }
        }

        return response()->json(['data' => ['saved' => true]]);
    }

    public function updateAuth(Request $request): JsonResponse
    {
        $data = $request->validate([
            'otp_enabled' => 'nullable|boolean',
            'password_min_length' => 'nullable|integer|min:6',
        ]);
        foreach ($data as $k => $v) {
            SystemSetting::set('auth_'.$k, (string) $v, 'auth');
        }

        return response()->json(['data' => ['saved' => true]]);
    }
}
