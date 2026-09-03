<?php

namespace Modules\Integrations\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Integrations\Services\Bale\BaleApiClient;

class BaleIntegrationController extends Controller
{
    public function sendMessage(Request $request): JsonResponse
    {
        $data = $request->validate([
            'chat_id' => 'required|string',
            'text' => 'required|string',
        ]);
        $token = (string) config('integrations.bale.token', '');
        $client = new BaleApiClient($token);
        if (! $client->hasToken()) {
            return response()->json(['message' => 'BALE_BOT_TOKEN not configured'], 422);
        }
        $out = $client->sendMessage([
            'chat_id' => $data['chat_id'],
            'text' => $data['text'],
        ]);

        return response()->json(['data' => ['sent' => true, 'response' => $out]]);
    }

    public function sendBulkMessage(Request $request): JsonResponse
    {
        $data = $request->validate([
            'recipients' => 'required|array',
            'text' => 'required|string',
        ]);
        $token = (string) config('integrations.bale.token', '');
        $client = new BaleApiClient($token);
        if (! $client->hasToken()) {
            return response()->json(['message' => 'BALE_BOT_TOKEN not configured'], 422);
        }
        $sent = 0;
        foreach ($data['recipients'] as $rid) {
            $r = $client->sendMessage(['chat_id' => (string) $rid, 'text' => $data['text']]);
            if (is_array($r) && ($r['ok'] ?? false)) {
                $sent++;
            }
        }

        return response()->json(['data' => ['sent' => $sent]]);
    }

    public function webhook(Request $request): JsonResponse
    {
        $secret = (string) config('integrations.bale.webhook_secret', env('BALE_WEBHOOK_SECRET', ''));
        if ($secret === '') {
            return response()->json(['message' => 'Webhook secret not configured'], 503);
        }

        $raw = $request->getContent();
        $sigHeader = (string) ($request->header('X-Bale-Signature')
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
        Log::channel('single')->info('bale.webhook', [
            'keys' => is_array($payload) ? array_keys($payload) : [],
            'bytes' => strlen($raw),
        ]);

        return response()->json(['ok' => true]);
    }
}
