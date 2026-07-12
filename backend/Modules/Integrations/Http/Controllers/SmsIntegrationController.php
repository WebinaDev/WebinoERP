<?php

namespace Modules\Integrations\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Jobs\SendSmsJob;
use Illuminate\Support\Facades\Log;
use Modules\Core\Entities\SystemSetting;
use Modules\Integrations\Entities\IntegrationSetting;

class SmsIntegrationController extends Controller
{
    private const INTEGRATION = 'sms';

    private const KEY_SETTINGS = 'settings';

    public function getSettings(): JsonResponse
    {
        return response()->json([
            'data' => $this->mergedSettings(),
        ]);
    }

    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'to' => 'required|string',
            'message' => 'required|string|max:2000',
        ]);

        $settings = $this->mergedSettings();
        $provider = $settings['provider'] ?? config('integrations.sms.default', 'log');

        Log::channel('single')->info('sms.send', [
            'provider' => $provider,
            'to' => $data['to'],
            'message' => $data['message'],
        ]);

        if (in_array($provider, ['melipayamak', 'parsgreen'], true)) {
            SendSmsJob::dispatch($provider, $data['to'], $data['message'], $settings);

            return response()->json([
                'data' => ['queued' => true, 'provider' => $provider],
                'message' => 'SMS queued',
            ]);
        }

        $queued = $provider !== 'disabled';

        return response()->json([
            'data' => [
                'queued' => $queued,
                'provider' => $provider,
            ],
            'message' => $provider === 'log' || $provider === 'stub'
                ? 'SMS logged (configure a real provider in integration settings)'
                : 'SMS queued',
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'provider' => 'nullable|string|max:50',
            'api_key' => 'nullable|string|max:500',
            'username' => 'nullable|string|max:191',
            'password' => 'nullable|string|max:500',
            'sender' => 'nullable|string|max:50',
            'pattern_id' => 'nullable|string|max:100',
        ]);

        $current = IntegrationSetting::getJson(self::INTEGRATION, self::KEY_SETTINGS, []);
        $merged = array_merge($current, array_filter($payload, fn ($v) => $v !== null));
        IntegrationSetting::putJson(self::INTEGRATION, self::KEY_SETTINGS, $merged);
        $this->mirrorToSystemSettings($merged);

        return response()->json([
            'data' => $this->mergedSettings(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function mergedSettings(): array
    {
        $fromIntegration = IntegrationSetting::getJson(self::INTEGRATION, self::KEY_SETTINGS, []);
        $fromSystem = SystemSetting::getByGroup('sms');
        $provider = $fromIntegration['provider'] ?? $fromSystem['provider'] ?? config('integrations.sms.default', 'log');

        return array_merge(
            ['provider' => $provider],
            $fromSystem,
            is_array($fromIntegration) ? $fromIntegration : [],
        );
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function mirrorToSystemSettings(array $settings): void
    {
        foreach (['provider', 'api_key', 'sender', 'username', 'password', 'pattern_id'] as $key) {
            if (array_key_exists($key, $settings) && $settings[$key] !== '') {
                SystemSetting::set($key, (string) $settings[$key], 'sms');
            }
        }
    }
}
