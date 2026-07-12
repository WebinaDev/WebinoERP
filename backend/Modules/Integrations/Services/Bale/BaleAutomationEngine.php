<?php

namespace Modules\Integrations\Services\Bale;

use Carbon\Carbon;
use Modules\Integrations\Entities\BaleAutomationQueue;
use Modules\Integrations\Entities\BaleEvent;
use Modules\Integrations\Entities\BaleLead;

/**
 * امتیازدهی لید، صف پیام‌های زمان‌بندی‌شده و پردازش صف.
 */
class BaleAutomationEngine
{
    /**
     * @var array<string, int>
     */
    private const SCORE_MAP = [
        'start' => 5,
        'reply' => 3,
        'plan_click' => 12,
        'profile_complete' => 15,
        'business_submit' => 20,
        'purchase' => 30,
    ];

    public function recordTimelineEvent(string $chatId, string $eventType, array $payload = []): void
    {
        BaleEvent::query()->create([
            'chat_id' => $chatId,
            'event_type' => $eventType,
            'payload' => $payload ?: null,
        ]);
    }

    public function upsertLeadScore(string $chatId, string $eventType): void
    {
        $delta = self::SCORE_MAP[$eventType] ?? 1;
        $lead = BaleLead::query()->where('chat_id', $chatId)->first();
        $now = now();
        if ($lead) {
            $score = min(255, (int) $lead->score + $delta);
            $lead->update([
                'score' => $score,
                'funnel_stage' => $this->scoreToStage($score),
                'last_event_at' => $now,
            ]);
        } else {
            BaleLead::query()->create([
                'chat_id' => $chatId,
                'score' => $delta,
                'funnel_stage' => $this->scoreToStage($delta),
                'last_event_at' => $now,
            ]);
        }
    }

    public function ingestEvent(string $chatId, string $eventType, array $payload = []): void
    {
        $this->recordTimelineEvent($chatId, $eventType, $payload);
        $this->upsertLeadScore($chatId, $eventType);
        $this->scheduleDefaultSequences($chatId, $eventType);
    }

    /**
     * هم‌تراز WebinaBaleBusiness\Automation\Engine::schedule_default_sequences
     */
    private function scheduleDefaultSequences(string $chatId, string $eventType): void
    {
        $rules = match ($eventType) {
            'start' => [
                ['step' => 'd0_welcome', 'hours' => 0, 'message' => 'خوش اومدی. برای شروع لطفا پروفایل و نیاز کسب‌وکارت رو کامل کن.'],
                ['step' => 'd1_followup', 'hours' => 24, 'message' => 'یادآوری دوستانه: تکمیل پروفایل باعث فعال شدن پیشنهاد اختصاصی می‌شود.'],
                ['step' => 'd3_followup', 'hours' => 72, 'message' => 'هنوز همراهیم؛ اگر آماده‌ای برای شروع، همین پیام رو پاسخ بده.'],
            ],
            'plan_click' => [
                ['step' => 'd0_plan', 'hours' => 3, 'message' => 'برای انتخاب بهتر پلن، مشخصات کسب‌وکار را ارسال کن تا دقیق پیشنهاد بدهیم.'],
                ['step' => 'd1_plan', 'hours' => 24, 'message' => 'پیشنهاد ویژه پلن همچنان فعال است. اگر سوالی داری همینجا بپرس.'],
                ['step' => 'd3_plan', 'hours' => 72, 'message' => 'آخرین یادآوری پلن: با فعال‌سازی امروز، onboarding سریع انجام می‌شود.'],
            ],
            'business_submit' => [
                ['step' => 'd0_sales', 'hours' => 0, 'message' => 'ثبت کسب‌وکار انجام شد. کارشناس فروش خیلی سریع با شما هماهنگ می‌کند.'],
                ['step' => 'd1_sales', 'hours' => 24, 'message' => 'برای تسریع فرایند، اگر سوالی داری در همین چت ارسال کن.'],
            ],
            default => [],
        };
        $base = Carbon::now('UTC');
        foreach ($rules as $rule) {
            $scheduled = (clone $base)->addHours((int) $rule['hours']);
            BaleAutomationQueue::query()->create([
                'chat_id' => $chatId,
                'trigger_key' => $eventType,
                'step_key' => (string) $rule['step'],
                'scheduled_for' => $scheduled,
                'status' => 'queued',
                'payload' => ['message' => (string) $rule['message']],
            ]);
        }
    }

    private function scoreToStage(int $score): string
    {
        if ($score >= 60) {
            return 'sales-ready';
        }
        if ($score >= 40) {
            return 'hot';
        }
        if ($score >= 20) {
            return 'warm';
        }

        return 'new';
    }

    /**
     * @return array{processed: int, failed: int}
     */
    public function processDueQueue(BaleApiClient $client): array
    {
        $processed = 0;
        $failed = 0;
        $items = BaleAutomationQueue::query()
            ->where('status', 'queued')
            ->where('scheduled_for', '<=', now())
            ->orderBy('id')
            ->limit(100)
            ->get();

        foreach ($items as $item) {
            $chatId = (string) $item->chat_id;
            $payload = is_array($item->payload) ? $item->payload : [];
            $text = (string) ($payload['message'] ?? '');
            $ok = $chatId !== '' && $text !== '' && $client->hasToken();
            if ($ok) {
                $res = $client->sendMessage(['chat_id' => $chatId, 'text' => $text]);
                $ok = is_array($res) && ! empty($res['ok']);
            }
            $item->update(['status' => $ok ? 'done' : 'failed']);
            if ($ok) {
                $processed++;
            } else {
                $failed++;
            }
        }

        return ['processed' => $processed, 'failed' => $failed];
    }
}
