<?php

namespace Modules\Platform\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Modules\Platform\Entities\PlatformNotificationChannel;

class NotificationChannelController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => PlatformNotificationChannel::query()->latest()->get(), 'message' => null, 'meta' => null, 'errors' => null]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'type' => 'required|in:email,discord,telegram,slack',
            'config' => 'required|array',
            'enabled' => 'nullable|boolean',
        ]);
        $row = PlatformNotificationChannel::query()->create($data);
        return response()->json(['success' => true, 'data' => $row, 'message' => null, 'meta' => null, 'errors' => null], 201);
    }

    public function test(PlatformNotificationChannel $channel): JsonResponse
    {
        $cfg = $channel->config ?? [];
        $msg = 'Webino Platform test notification';
        if ($channel->type === 'discord' || $channel->type === 'slack') {
            $url = $cfg['webhook_url'] ?? null;
            if ($url) Http::post($url, ['content' => $msg, 'text' => $msg]);
        }
        if ($channel->type === 'telegram') {
            $token = $cfg['bot_token'] ?? '';
            $chat = $cfg['chat_id'] ?? '';
            if ($token && $chat) {
                Http::post("https://api.telegram.org/bot{$token}/sendMessage", ['chat_id' => $chat, 'text' => $msg]);
            }
        }
        return response()->json(['success' => true, 'data' => ['sent' => true], 'message' => null, 'meta' => null, 'errors' => null]);
    }

    public function destroy(PlatformNotificationChannel $channel): JsonResponse
    {
        $channel->delete();
        return response()->json(['success' => true, 'data' => ['deleted' => true], 'message' => null, 'meta' => null, 'errors' => null]);
    }
}
