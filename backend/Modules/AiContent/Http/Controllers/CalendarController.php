<?php

namespace Modules\AiContent\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AiContent\Entities\AiCalendarSlot;
use Modules\AiContent\Entities\AiJob;

class CalendarController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AiCalendarSlot::query()->orderBy('slot_date')->orderBy('id');
        if ($from = $request->string('from')->toString()) {
            $query->where('slot_date', '>=', $from);
        }
        if ($to = $request->string('to')->toString()) {
            $query->where('slot_date', '<=', $to);
        }

        return response()->json([
            'items' => $query->get()->map(fn (AiCalendarSlot $s) => $this->serialize($s))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'slot_date' => 'required|date',
            'content_type' => 'nullable|string|max:20',
            'topic' => 'required|string|max:500',
            'focus_keyword' => 'nullable|string|max:255',
            'secondary_keywords' => 'nullable|string',
            'category_id' => 'nullable|integer',
            'product_id' => 'nullable|integer',
            'status' => 'nullable|string|max:20',
            'notes' => 'nullable|string',
        ]);

        $slot = AiCalendarSlot::query()->create([
            'slot_date' => $data['slot_date'],
            'content_type' => $data['content_type'] ?? 'blog',
            'topic' => $data['topic'],
            'focus_keyword' => $data['focus_keyword'] ?? $data['topic'],
            'secondary_keywords' => $data['secondary_keywords'] ?? '',
            'category_id' => $data['category_id'] ?? 0,
            'product_id' => $data['product_id'] ?? 0,
            'status' => $data['status'] ?? 'planned',
            'notes' => $data['notes'] ?? '',
        ]);

        return response()->json($this->serialize($slot), 201);
    }

    public function bulk(Request $request): JsonResponse
    {
        $data = $request->validate([
            'topics' => 'required|string',
            'start_date' => 'required|date',
            'content_type' => 'nullable|string|max:20',
            'focus_keyword' => 'nullable|string|max:255',
            'category_id' => 'nullable|integer',
        ]);

        $lines = preg_split('/\r\n|\r|\n/', trim($data['topics'])) ?: [];
        $date = \Carbon\Carbon::parse($data['start_date']);
        $created = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = array_map('trim', explode('|', $line, 2));
            $topic = $parts[0];
            $focus = $parts[1] ?? ($data['focus_keyword'] ?? $topic);
            $slot = AiCalendarSlot::query()->create([
                'slot_date' => $date->toDateString(),
                'content_type' => $data['content_type'] ?? 'blog',
                'topic' => $topic,
                'focus_keyword' => $focus,
                'category_id' => $data['category_id'] ?? 0,
                'status' => 'planned',
            ]);
            $created[] = $this->serialize($slot);
            $date->addDay();
        }

        return response()->json(['created' => count($created), 'items' => $created]);
    }

    public function destroy(int $id): JsonResponse
    {
        AiCalendarSlot::query()->whereKey($id)->delete();

        return response()->json(['ok' => true]);
    }

    public function runDue(): JsonResponse
    {
        $due = AiCalendarSlot::query()
            ->where('slot_date', '<=', now()->toDateString())
            ->where('status', 'planned')
            ->limit(50)
            ->get();

        foreach ($due as $slot) {
            $job = AiJob::query()->create([
                'job_type' => $slot->content_type,
                'target_type' => 'calendar',
                'target_id' => $slot->id,
                'payload' => [
                    'topic' => $slot->topic,
                    'focus_keyword' => $slot->focus_keyword,
                    'title' => $slot->topic,
                ],
                'status' => 'pending',
                'provider' => 'stub',
                'model' => 'placeholder',
            ]);
            $slot->update(['status' => 'queued', 'job_id' => $job->id]);
        }

        return response()->json(['ok' => true, 'count' => $due->count()]);
    }

    private function serialize(AiCalendarSlot $slot): array
    {
        return [
            'id' => $slot->id,
            'slot_date' => optional($slot->slot_date)?->format('Y-m-d') ?? (string) $slot->slot_date,
            'content_type' => $slot->content_type,
            'topic' => $slot->topic,
            'focus_keyword' => $slot->focus_keyword,
            'secondary_keywords' => (string) ($slot->secondary_keywords ?? ''),
            'category_id' => (int) $slot->category_id,
            'product_id' => (int) $slot->product_id,
            'status' => $slot->status,
            'notes' => (string) ($slot->notes ?? ''),
        ];
    }
}
