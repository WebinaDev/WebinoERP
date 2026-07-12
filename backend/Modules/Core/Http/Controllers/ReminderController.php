<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Entities\CoreNotification;
use Modules\Core\Entities\CoreReminder;

class ReminderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 30), 100);
        $q = CoreReminder::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('remind_at');

        if ($request->boolean('active_only')) {
            $q->whereNull('dismissed_at');
        }

        return response()->json(['data' => $q->paginate($perPage)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => 'nullable|integer|exists:users,id',
            'title' => 'required|string|max:191',
            'body' => 'nullable|string',
            'channel' => 'nullable|string|in:in_app,email,sms,bale,telegram',
            'payload' => 'nullable|array',
            'remind_at' => 'required|date',
            'remindable_type' => 'nullable|string|max:191',
            'remindable_id' => 'nullable|integer|min:1',
        ]);

        $targetUserId = (int) ($data['user_id'] ?? $request->user()->id);
        if ($targetUserId !== (int) $request->user()->id && ! $request->user()->hasRole('system_manager')) {
            return response()->json(['data' => ['message' => 'دسترسی کافی ندارید']], 403);
        }

        $row = CoreReminder::query()->create([
            'user_id' => $targetUserId,
            'title' => $data['title'],
            'body' => $data['body'] ?? null,
            'channel' => $data['channel'] ?? 'in_app',
            'payload' => $data['payload'] ?? [],
            'remind_at' => $data['remind_at'],
            'remindable_type' => $data['remindable_type'] ?? null,
            'remindable_id' => $data['remindable_id'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $row], 201);
    }

    public function snooze(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'minutes' => 'nullable|integer|min:1|max:10080',
            'snoozed_until' => 'nullable|date',
        ]);

        $row = CoreReminder::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($id)
            ->firstOrFail();

        $until = $data['snoozed_until'] ?? now()->addMinutes((int) ($data['minutes'] ?? 30));
        $row->update(['snoozed_until' => $until]);

        return response()->json(['data' => $row->fresh()]);
    }

    public function dismiss(Request $request, int $id): JsonResponse
    {
        $row = CoreReminder::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($id)
            ->firstOrFail();

        $row->update(['dismissed_at' => now()]);

        return response()->json(['data' => ['id' => $row->id, 'dismissed' => true]]);
    }

    public function runDueReminders(): int
    {
        $rows = CoreReminder::query()
            ->whereNull('dismissed_at')
            ->whereNull('sent_at')
            ->where(function ($q) {
                $q->whereNull('snoozed_until')->orWhere('snoozed_until', '<=', now());
            })
            ->where('remind_at', '<=', now())
            ->limit(200)
            ->get();

        $count = 0;
        foreach ($rows as $row) {
            CoreNotification::query()->create([
                'user_id' => $row->user_id,
                'type' => 'reminder',
                'data' => [
                    'reminder_id' => $row->id,
                    'title' => $row->title,
                    'body' => $row->body,
                    'channel' => $row->channel,
                    'payload' => $row->payload ?? [],
                    'remindable_type' => $row->remindable_type,
                    'remindable_id' => $row->remindable_id,
                    'remind_at' => optional($row->remind_at)?->toISOString(),
                ],
                'is_read' => false,
            ]);

            $row->update(['sent_at' => now()]);
            $count++;
        }

        return $count;
    }
}
