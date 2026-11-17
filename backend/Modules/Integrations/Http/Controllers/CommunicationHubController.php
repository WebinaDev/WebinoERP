<?php

namespace Modules\Integrations\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Integrations\Entities\CommunicationEventCatalog;
use Modules\Integrations\Entities\CommunicationTemplate;
use Modules\Integrations\Entities\UserNotificationPreference;
use Modules\Integrations\Services\NotificationFanout;

class CommunicationHubController extends Controller
{
    public function events(): JsonResponse
    {
        $events = CommunicationEventCatalog::query()->where('is_active', true)->get();

        return response()->json(['data' => $events]);
    }

    public function templates(): JsonResponse
    {
        $templates = CommunicationTemplate::all();

        return response()->json(['data' => $templates]);
    }

    public function dispatchTest(Request $request, NotificationFanout $fanout): JsonResponse
    {
        $data = $request->validate([
            'event_key' => 'required|string',
            'title' => 'required|string|max:150',
            'body' => 'required|string',
            'priority' => 'nullable|string|in:critical,high,normal,low',
            'channels' => 'nullable|array',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
        ]);

        $res = $fanout->dispatch([
            'event_key' => $data['event_key'],
            'title' => $data['title'],
            'body' => $data['body'],
            'priority' => $data['priority'] ?? 'normal',
            'channels' => $data['channels'] ?? ['in_app', 'sms', 'email', 'chat'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'user_id' => auth()->id(),
        ]);

        return response()->json([
            'data' => $res,
            'message' => 'ارسال پیام به کانال‌های ارتباطی با موفقیت اورکستریت شد.',
        ]);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event_key' => 'required|string',
            'channel_in_app' => 'nullable|boolean',
            'channel_sms' => 'nullable|boolean',
            'channel_email' => 'nullable|boolean',
            'channel_chat' => 'nullable|boolean',
            'quiet_hours_start' => 'nullable|string',
            'quiet_hours_end' => 'nullable|string',
        ]);

        $pref = UserNotificationPreference::updateOrCreate(
            [
                'user_id' => auth()->id(),
                'event_key' => $data['event_key'],
            ],
            $data
        );

        return response()->json([
            'data' => $pref,
            'message' => 'ترجیحات دریافت اعلان با موفقیت به‌روزرسانی شد.',
        ]);
    }
}
