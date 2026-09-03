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
        $general = $this->safeGroup('general');
        $modules = $this->safeGroup('modules');
        $branding = $this->safeGroup('branding');

        return response()->json([
            'data' => [
                'general' => $general,
                'modules' => $modules,
                'branding' => $branding,
                'auth' => $this->safeGroup('auth'),
                'sms' => $this->smsSettingsForUi(),
                'payment' => $this->safeGroup('payment'),
                'uiTheme' => $general['ui_theme'] ?? null,
                'uiAccent' => $general['ui_accent'] ?? null,
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
    public function saveUiPreferences(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'ui_theme' => 'nullable|string|in:light,dark,system',
            'ui_accent' => 'nullable|string|max:32',
            'settings' => 'nullable|array',
            'group' => 'nullable|string|max:50',
        ]);

        if (isset($payload['settings'])) {
            return $this->update($request);
        }

        if (! empty($payload['ui_theme'])) {
            SystemSetting::set('ui_theme', $payload['ui_theme'], 'general');
        }
        if (! empty($payload['ui_accent'])) {
            SystemSetting::set('ui_accent', $payload['ui_accent'], 'general');
        }

        return response()->json(['data' => ['saved' => true]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function safeGroup(string $group): array
    {
        try {
            return SystemSetting::getByGroup($group);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function smsSettingsForUi(): array
    {
        try {
            $fromSystem = SystemSetting::getByGroup('sms');
        } catch (\Throwable) {
            $fromSystem = [];
        }

        try {
            $fromIntegration = IntegrationSetting::getJson('sms', 'settings', []);
        } catch (\Throwable) {
            $fromIntegration = [];
        }
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
            '2fa_required' => 'nullable|boolean',
            'password_min_length' => 'nullable|integer|min:6',
        ]);
        foreach ($data as $k => $v) {
            SystemSetting::set('auth_'.$k, (string) $v, 'auth');
        }

        return response()->json(['data' => ['saved' => true]]);
    }
}
