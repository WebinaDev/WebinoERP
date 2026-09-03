<?php

namespace Modules\Integrations\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramIntegrationController extends Controller
{
    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'chat_id' => 'required|string',
            'text' => 'required|string|max:4096',
        ]);
        $token = config('integrations.telegram.token');
        if (! $token) {
            return response()->json(['message' => 'TELEGRAM_BOT_TOKEN not configured'], 422);
        }
        $url = 'https://api.telegram.org/bot'.$token.'/sendMessage';
        $res = Http::asJson()->post($url, [
            'chat_id' => $data['chat_id'],
            'text' => $data['text'],
        ]);
        if (! $res->successful()) {
            return response()->json(['message' => $res->body()], 502);
        }

        return response()->json(['data' => $res->json()]);
    }

    public function webhook(Request $request): JsonResponse
    {
        $secret = (string) config('integrations.telegram.webhook_secret', env('TELEGRAM_WEBHOOK_SECRET', ''));
        if ($secret === '') {
            return response()->json(['message' => 'Webhook secret not configured'], 503);
        }

        $raw = $request->getContent();
        $sigHeader = (string) ($request->header('X-Telegram-Bot-Api-Secret-Token')
            ?: $request->header('X-Webhook-Secret')
            ?: $request->header('X-Webino-Signature')
            ?: '');
        if (str_starts_with($sigHeader, 'sha256=')) {
            $sigHeader = substr($sigHeader, 7);
        }

        $validPlain = $sigHeader !== '' && hash_equals($secret, $sigHeader);
        $expectedHmac = hash_hmac('sha256', $raw, $secret);
        $validHmac = $sigHeader !== '' && hash_equals($expectedHmac, $sigHeader);

        if (! $validPlain && ! $validHmac) {
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $payload = json_decode($raw, true);
        Log::channel('single')->info('telegram.webhook', [
            'keys' => is_array($payload) ? array_keys($payload) : [],
            'bytes' => strlen($raw),
        ]);

        return response()->json(['ok' => true]);
    }
}
