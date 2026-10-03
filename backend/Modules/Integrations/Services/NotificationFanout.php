<?php

namespace Modules\Integrations\Services;

use Modules\Core\Entities\CoreNotification;
use Modules\Integrations\Entities\ChannelDelivery;
use Modules\Integrations\Entities\ChatBridge;

class NotificationFanout
{
    public function __construct(
        private readonly SmsPriorityGate $sms,
        private readonly ChatBridgeService $chat,
        private readonly ModirPayamakEdgeClient $payamak,
    ) {}

    /**
     * @param  array{title?: string, body?: string, priority?: string, event_key?: string, user_id?: int|null, phone?: string|null, channels?: list<string>}  $event
     * @return array<string, mixed>
     */
    public function dispatch(array $event): array
    {
        $priority = (string) ($event['priority'] ?? 'normal');
        $eventKey = (string) ($event['event_key'] ?? 'general');
        $title = (string) ($event['title'] ?? '');
        $body = (string) ($event['body'] ?? '');
        $userId = isset($event['user_id']) ? (int) $event['user_id'] : null;
        $channels = $event['channels'] ?? ['in_app', 'chat', 'sms'];
        $results = [];

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
            $results['in_app'] = $this->log('in_app', $eventKey, $priority, 'delivered', 'stored', $body, $userId, ['notification_id' => $note->id]);
        }

        if (in_array('chat', $channels, true)) {
            $bridges = ChatBridge::query()->where('enabled', true)->get();
            $sent = 0;
            foreach ($bridges as $bridge) {
                $ok = $this->chat->send($bridge, trim($title."\n".$body), null);
                if ($ok) {
                    $sent++;
                }
            }
            $results['chat'] = $this->log('chat', $eventKey, $priority, $sent > 0 ? 'delivered' : 'skipped', $sent > 0 ? 'bridge' : 'no_bridge', $body, $userId, ['sent' => $sent]);
        }

        if (in_array('sms', $channels, true)) {
            $decision = $this->sms->decide($priority, $eventKey);
            if (! $decision['send']) {
                $results['sms'] = $this->log('sms', $eventKey, $priority, 'suppressed', $decision['reason'], $body, $userId, $decision);
            } elseif (empty($event['phone'])) {
                $results['sms'] = $this->log('sms', $eventKey, $priority, 'skipped', 'no_phone', $body, $userId, $decision);
            } elseif ($this->payamak->isConfigured()) {
                $remote = $this->payamak->sendWebservice($this->payamak->defaultFrom(), trim($title.' '.$body), [(string) $event['phone']]);
                $results['sms'] = $this->log('sms', $eventKey, $priority, $remote['ok'] ? 'sent' : 'failed', 'modirpayamak', $body, $userId, ['remote' => $remote['message'] ?? '']);
            } else {
                $results['sms'] = $this->log('sms', $eventKey, $priority, 'queued', 'provider_not_configured', $body, $userId, $decision);
            }
        }

        return ['results' => $results];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function log(string $channel, string $eventKey, string $priority, string $status, string $reason, string $body, ?int $userId, array $meta): array
    {
        $row = ChannelDelivery::query()->create([
            'user_id' => $userId,
            'channel' => $channel,
            'event_key' => $eventKey,
            'priority' => $priority,
            'status' => $status,
            'reason' => $reason,
            'body' => $body,
            'meta' => $meta,
        ]);

        return ['id' => $row->id, 'status' => $status, 'reason' => $reason];
    }
}
