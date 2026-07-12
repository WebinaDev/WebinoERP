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
        Log::channel('single')->info('telegram.webhook', $request->all());

        return response()->json(['ok' => true]);
    }
}
