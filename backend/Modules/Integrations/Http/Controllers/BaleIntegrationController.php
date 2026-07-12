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
        Log::channel('single')->info('bale.webhook', $request->all());

        return response()->json(['ok' => true]);
    }
}
