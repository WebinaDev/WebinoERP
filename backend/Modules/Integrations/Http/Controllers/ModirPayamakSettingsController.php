<?php

namespace Modules\Integrations\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Integrations\Entities\IntegrationSetting;

class ModirPayamakSettingsController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->payload()]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enabled' => 'nullable|boolean',
            'api_key' => 'nullable|string|max:500',
            'default_from' => 'nullable|string|max:30',
        ]);

        $apiKey = isset($data['api_key']) ? trim((string) $data['api_key']) : '';
        if ($apiKey !== '') {
            IntegrationSetting::putString('modirpayamak', 'api_key', $apiKey);
        }

        if (array_key_exists('default_from', $data)) {
            IntegrationSetting::putString('modirpayamak', 'default_from', (string) ($data['default_from'] ?? ''));
        }

        if (array_key_exists('enabled', $data)) {
            IntegrationSetting::putString('modirpayamak', 'enabled', $data['enabled'] ? '1' : '0');
        } elseif ($apiKey !== '') {
            // Saving a new key without an explicit flag should activate the integration.
            IntegrationSetting::putString('modirpayamak', 'enabled', '1');
        }

        return response()->json([
            'message' => 'Settings saved',
            'data' => $this->payload(),
        ]);
    }

    /**
     * @return array{enabled: bool, default_from: string, has_api_key: bool, api_key_masked: string|null}
     */
    protected function payload(): array
    {
        $rawKey = IntegrationSetting::getString('modirpayamak', 'api_key', '');
        $hasKey = $rawKey !== '';

        return [
            'enabled' => IntegrationSetting::getString('modirpayamak', 'enabled', '0') === '1',
            'default_from' => IntegrationSetting::getString('modirpayamak', 'default_from', ''),
            'has_api_key' => $hasKey,
            // Never echo the secret; only a short mask for UI confirmation after refresh.
            'api_key_masked' => $hasKey ? $this->maskSecret($rawKey) : null,
        ];
    }

    protected function maskSecret(string $value): string
    {
        $len = mb_strlen($value);
        if ($len <= 4) {
            return str_repeat('*', $len);
        }
        if ($len <= 8) {
            return str_repeat('*', $len - 2).mb_substr($value, -2);
        }

        return mb_substr($value, 0, 2).str_repeat('*', min(12, $len - 4)).mb_substr($value, -2);
    }
}
