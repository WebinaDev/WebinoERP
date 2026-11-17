<?php

namespace Modules\Integrations\Services;

use Illuminate\Support\Facades\Mail;
use Modules\Core\Entities\CoreNotification;
use Modules\Integrations\Entities\ChannelDelivery;
use Modules\Integrations\Entities\ChatBridge;
use Modules\Integrations\Entities\UserNotificationPreference;

/**
 * Enterprise Multi-Channel Communication Orchestrator.
 * Policy Engine, Deduplication Window, Quiet Hours, and Fanout to In-App, SMS, Email, and Chat.
 */
class NotificationFanout
{
    public function __construct(
        private readonly SmsPriorityGate $sms,
        private readonly ChatBridgeService $chat,
        private readonly ModirPayamakEdgeClient $payamak,
    ) {}

    /**
     * @param  array{
     *   title?: string,
     *   body?: string,
     *   priority?: string,
     *   event_key?: string,
     *   user_id?: int|null,
     *   phone?: string|null,
     *   email?: string|null,
     *   channels?: list<string>,
     *   variables?: array<string, mixed>
     * }  $event
     * @return array<string, mixed>
     */
    public function dispatch(array $event): array
    {
        $priority = (string) ($event['priority'] ?? 'normal');
        $eventKey = (string) ($event['event_key'] ?? 'general');
        $title = (string) ($event['title'] ?? '');
        $body = (string) ($event['body'] ?? '');
        $userId = isset($event['user_id']) ? (int) $event['user_id'] : null;
        $phone = $event['phone'] ?? null;
        $email = $event['email'] ?? null;
        $channels = $event['channels'] ?? ['in_app', 'chat', 'sms', 'email'];
        $results = [];

        // ۱. ممانعت از ارسال تکراری (Deduplication Window - 10 دقیقه)
        $dedupKey = hash('sha256', "{$eventKey}:{$userId}:{$phone}:{$email}:" . substr($body, 0, 50));
        $recentDuplicate = ChannelDelivery::query()
            ->where('dedup_hash', $dedupKey)
            ->where('created_at', '>=', now()->subMinutes(10))
            ->exists();

        if ($recentDuplicate) {
            return [
                'status' => 'suppressed',
                'reason' => 'deduplication_window_active',
                'dedup_hash' => $dedupKey,
            ];
        }

        // ۲. بازرسی ساعات سکوت کاربر (Quiet Hours Gate)
        $isQuietHour = false;
        if ($userId && $priority !== 'critical') {
            $pref = UserNotificationPreference::query()
                ->where('user_id', $userId)
                ->where('event_key', $eventKey)
                ->first();

            if ($pref && $pref->quiet_hours_start && $pref->quiet_hours_end) {
                $nowTime = now()->format('H:i:s');
                if ($nowTime >= $pref->quiet_hours_start && $nowTime <= $pref->quiet_hours_end) {
                    $isQuietHour = true;
                }
            }
        }

        // ۳. کانال اعلان درون‌برنامه‌ای (In-App)
        if (in_array('in_app', $channels, true) && $userId) {
            $note = CoreNotification::query()->create([
                'user_id' => $userId,
                'type' => $eventKey,
                'data' => [
                    'title' => $title,
                    'body' => $body,
                    'priority' => $priority,
                    'source' => 'fanout',
                ],
                'is_read' => false,
            ]);
            $results['in_app'] = $this->log('in_app', $eventKey, $priority, 'delivered', 'stored', $body, $userId, null, ['notification_id' => $note->id], $dedupKey);
        }

        // ۴. کانال ایمیل تراکنشی (Transactional Email)
        if (in_array('email', $channels, true) && ! empty($email)) {
            if ($isQuietHour) {
                $results['email'] = $this->log('email', $eventKey, $priority, 'suppressed', 'quiet_hours_active', $body, $userId, $email, [], $dedupKey);
            } else {
                try {
                    Mail::raw($body, function ($message) use ($email, $title) {
                        $message->to($email)
                            ->subject($title ?: 'اعلان رسمی سامانه WebinoERP');
                    });
                    $results['email'] = $this->log('email', $eventKey, $priority, 'sent', 'smtp_dispatched', $body, $userId, $email, [], $dedupKey);
                } catch (\Throwable $e) {
                    $results['email'] = $this->log('email', $eventKey, $priority, 'failed', $e->getMessage(), $body, $userId, $email, [], $dedupKey);
                }
            }
        }

        // ۵. کانال چت‌بریج (Chat Bridges: Bale, Telegram, Slack)
        if (in_array('chat', $channels, true)) {
            $bridges = ChatBridge::query()->where('enabled', true)->get();
            $sent = 0;
            foreach ($bridges as $bridge) {
                $ok = $this->chat->send($bridge, trim($title . "\n" . $body), null);
                if ($ok) {
                    $sent++;
                }
            }
            $results['chat'] = $this->log('chat', $eventKey, $priority, $sent > 0 ? 'delivered' : 'skipped', $sent > 0 ? 'bridge' : 'no_bridge', $body, $userId, null, ['sent' => $sent], $dedupKey);
        }

        // ۶. کانال پیامک (SMS Gate & ModirPayamak)
        if (in_array('sms', $channels, true)) {
            $decision = $this->sms->decide($priority, $eventKey);
            if (! $decision['send'] || $isQuietHour) {
                $results['sms'] = $this->log('sms', $eventKey, $priority, 'suppressed', $isQuietHour ? 'quiet_hours' : $decision['reason'], $body, $userId, $phone, $decision, $dedupKey);
            } elseif (empty($phone)) {
                $results['sms'] = $this->log('sms', $eventKey, $priority, 'skipped', 'no_phone', $body, $userId, null, $decision, $dedupKey);
            } elseif ($this->payamak->isConfigured()) {
                $remote = $this->payamak->sendWebservice($this->payamak->defaultFrom(), trim($title . ' ' . $body), [(string) $phone]);
                $results['sms'] = $this->log('sms', $eventKey, $priority, $remote['ok'] ? 'sent' : 'failed', 'modirpayamak', $body, $userId, $phone, ['remote' => $remote['message'] ?? ''], $dedupKey);
            } else {
                $results['sms'] = $this->log('sms', $eventKey, $priority, 'queued', 'provider_not_configured', $body, $userId, $phone, $decision, $dedupKey);
            }
        }

        return ['results' => $results, 'dedup_hash' => $dedupKey];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function log(
        string $channel,
        string $eventKey,
        string $priority,
        string $status,
        string $reason,
        string $body,
        ?int $userId,
        ?string $recipient,
        array $meta,
        string $dedupHash
    ): array {
        $row = ChannelDelivery::query()->create([
            'user_id' => $userId,
            'recipient' => $recipient,
            'channel' => $channel,
            'event_key' => $eventKey,
            'priority' => $priority,
            'status' => $status,
            'reason' => $reason,
            'body' => $body,
            'meta' => $meta,
            'dedup_hash' => $dedupHash,
            'delivered_at' => in_array($status, ['delivered', 'sent'], true) ? now() : null,
        ]);

        return ['id' => $row->id, 'status' => $status, 'reason' => $reason];
    }
}
