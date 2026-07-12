<?php

namespace Modules\Integrations\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Integrations\Services\Bale\WoobaleSettingsStore;

/**
 * سازگاری با Woobale: POST /api/woobale/v1/webhook و GET /api/woobale/v1/health
 */
class WoobaleCompatController extends Controller
{
    public function __construct(
        private WoobaleSettingsStore $woobale,
    ) {}

    public function webhook(Request $request): JsonResponse
    {
        $ip = $request->ip() ?? '0';
        $key = 'woobale_wh_rl_'.md5($ip);
        if (RateLimiter::tooManyAttempts($key, 90)) {
            Log::channel('single')->notice('woobale.webhook_rate_limited', ['ip_hash' => substr(md5($ip), 0, 8)]);

            return response()->json(['ok' => false], 429);
        }
        RateLimiter::hit($key, 60);

        $incoming = $this->readSecretToken($request);
        $expected = $this->woobale->webhookSecret();
        $require = $this->woobale->requireWebhookSecret() && $expected !== '';

        if ($require && (! is_string($incoming) || ! hash_equals($expected, $incoming))) {
            Log::channel('single')->error('woobale.webhook_secret_mismatch', []);

            return response()->json(['ok' => false], 401);
        }

        $raw = $request->getContent();
        $data = json_decode($raw, true);
        if (! is_array($data)) {
            Log::channel('single')->error('woobale.webhook_invalid_json', []);

            return response()->json(['ok' => false], 400);
        }

        Log::channel('single')->info('woobale.webhook', ['keys' => array_keys($data)]);

        return response()->json(['ok' => true], 200);
    }

    public function health(Request $request): JsonResponse
    {
        $token = (string) $request->query('token', '');
        $expected = $this->woobale->healthCheckToken();
        if ($expected === '' || ! hash_equals($expected, $token)) {
            return response()->json(['ok' => false], 403);
        }

        return response()->json([
            'ok' => true,
            'version' => config('app.version', '1.0.0'),
        ]);
    }

    private function readSecretToken(Request $request): string
    {
        $h = $request->header('X-Telegram-Bot-Api-Secret-Token');
        if (is_string($h) && trim($h) !== '') {
            return trim($h);
        }

        return '';
    }
}
