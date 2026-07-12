<?php

namespace Modules\Integrations\Services;

use App\Models\User;
use Modules\Integrations\Entities\BaleCampaign;
use Modules\Integrations\Entities\BaleCampaignDelivery;
use Modules\Integrations\Entities\BaleEvent;
use Modules\Integrations\Entities\BaleLead;
use Modules\Integrations\Entities\BaleLog;
use Modules\Integrations\Services\Bale\BaleApiClient;
use Modules\Integrations\Services\Bale\BaleAutomationEngine;
use Modules\Integrations\Services\Bale\BaleSettingsStore;

/**
 * منطق REST بله کسب‌وکار (هم‌تراز webinocrm/v1/bale/*).
 */
class BaleBusinessService
{
    public function __construct(
        private BaleSettingsStore $settings,
        private BaleAutomationEngine $automation,
    ) {}

    public function makeClient(): BaleApiClient
    {
        return new BaleApiClient($this->settings->botToken());
    }

    public function settingsStore(): BaleSettingsStore
    {
        return $this->settings;
    }

    public function automation(): BaleAutomationEngine
    {
        return $this->automation;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSettingsForGet(): array
    {
        return $this->settings->forPublicApi();
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function updateSettings(array $body): array
    {
        return $this->settings->mergeAndSave($body);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function latestLogs(int $limit): array
    {
        $limit = max(1, min(500, $limit));

        return BaleLog::query()
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(function (BaleLog $row) {
                $ctx = $row->context;
                $decoded = is_string($ctx) ? json_decode($ctx, true) : null;

                return [
                    'id' => $row->id,
                    'level' => $row->level,
                    'log_type' => $row->log_type,
                    'context' => is_array($decoded) ? $decoded : $ctx,
                    'created_at' => $row->created_at?->toIso8601String(),
                ];
            })
            ->all();
    }

    public function webhookUrl(): string
    {
        return url('/api/webinocrm/v1/bale/webhook');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function setWebhook(): ?array
    {
        $client = $this->makeClient();
        $url = $this->webhookUrl();
        $secret = $this->settings->webhookSecret();

        return $client->setWebhook($url, $secret !== '' ? $secret : null);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function diagnosticsWebhookInfo(): ?array
    {
        return $this->makeClient()->getWebhookInfo();
    }

    public function diagnosticsTestLog(): void
    {
        $this->log('info', 'manual_test_log', ['source' => 'webinocrm_crm']);
    }

    /**
     * @return array<string, int>
     */
    public function diagnosticsStats(): array
    {
        return [
            'support_opened' => (int) BaleEvent::query()->where('event_type', 'support_opened')->count(),
            'support_item_clicked' => (int) BaleEvent::query()->where('event_type', 'support_item_clicked')->count(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function sendMessageToUser(int $userId, string $message): ?array
    {
        $message = trim($message);
        if ($userId <= 0 || $message === '') {
            return null;
        }
        $user = User::query()->find($userId);
        $chatId = $user ? trim((string) $user->bale_chat_id) : '';
        if ($chatId === '') {
            return null;
        }
        $client = $this->makeClient();
        $res = $client->sendMessage(['chat_id' => $chatId, 'text' => $message]);
        $this->log('info', 'bale_manual_message_sent', [
            'user_id' => $userId,
            'chat_id' => $chatId,
        ]);

        return $res;
    }

    /**
     * @param  list<string>  $roles
     * @return array{total: int, sent: int, failed: int}|null
     */
    public function sendBulkMessage(string $message, string $mode, array $roles): ?array
    {
        $message = trim($message);
        if ($message === '') {
            return null;
        }
        $mode = $mode === 'filtered' ? 'filtered' : 'all';
        $q = User::query()->whereNotNull('bale_chat_id')->where('bale_chat_id', '!=', '');
        if ($mode === 'filtered' && $roles !== []) {
            $q->whereHas('roles', fn ($r) => $r->whereIn('name', $roles));
        }
        $users = $q->limit(5000)->get(['id', 'bale_chat_id']);
        $client = $this->makeClient();
        $sent = 0;
        $failed = 0;
        foreach ($users as $user) {
            $chatId = (string) $user->bale_chat_id;
            if ($chatId === '') {
                continue;
            }
            $res = $client->sendMessage(['chat_id' => $chatId, 'text' => $message]);
            if (is_array($res) && ! empty($res['ok'])) {
                $sent++;
            } else {
                $failed++;
            }
        }
        $this->log('info', 'bale_bulk_message_sent', [
            'mode' => $mode,
            'total' => $users->count(),
            'sent' => $sent,
            'failed' => $failed,
        ]);

        return [
            'total' => $users->count(),
            'sent' => $sent,
            'failed' => $failed,
        ];
    }

    /**
     * @return array<string, int>
     */
    public function getStats(): array
    {
        $events = (int) BaleEvent::query()->count();
        $logs = (int) BaleLog::query()->count();
        $users = (int) BaleLead::query()->count();
        $started = (int) BaleEvent::query()
            ->where('event_type', 'conversation_started')
            ->distinct()
            ->count('chat_id');

        return [
            'total_events' => $events,
            'total_logs' => $logs,
            'total_users' => $users,
            'total_businesses' => 0,
            'started_users' => $started,
        ];
    }

    /**
     * @return array{chat_id: string, events: list<array<string, mixed>>, logs: list<array<string, mixed>>}|null
     */
    public function getUserLogs(string $chatId, int $limit): ?array
    {
        $chatId = trim($chatId);
        if ($chatId === '') {
            return null;
        }
        $limit = max(10, min(300, $limit));
        $events = BaleEvent::query()
            ->where('chat_id', $chatId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (BaleEvent $e) => [
                'id' => $e->id,
                'type' => $e->event_type,
                'payload' => $e->payload,
                'created_at' => $e->created_at?->toDateTimeString(),
            ])
            ->all();

        $like = '%'.$chatId.'%';
        $logs = BaleLog::query()
            ->where('context', 'like', $like)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(function (BaleLog $l) {
                $payload = $l->context;
                $decoded = is_string($payload) ? json_decode($payload, true) : null;

                return [
                    'id' => $l->id,
                    'type' => $l->log_type,
                    'payload' => is_array($decoded) ? $decoded : $payload,
                    'created_at' => $l->created_at?->toDateTimeString(),
                ];
            })
            ->all();

        return [
            'chat_id' => $chatId,
            'events' => $events,
            'logs' => $logs,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listCampaigns(): array
    {
        return BaleCampaign::query()
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn ($c) => $c->toArray())
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createCampaign(array $payload): ?int
    {
        $name = trim((string) ($payload['name'] ?? ''));
        $message = trim((string) ($payload['message_template'] ?? ''));
        if ($name === '' || $message === '') {
            return null;
        }
        $segment = preg_replace('/[^a-z0-9\-]/', '', (string) ($payload['segment_key'] ?? 'newcomer')) ?: 'newcomer';
        $variant = strtoupper(substr((string) ($payload['variant'] ?? 'A'), 0, 1));
        if (! in_array($variant, ['A', 'B'], true)) {
            $variant = 'A';
        }
        $row = BaleCampaign::query()->create([
            'name' => $name,
            'segment_key' => $segment,
            'variant' => $variant,
            'message_template' => $message,
            'cta_text' => trim((string) ($payload['cta_text'] ?? '')),
            'status' => 'draft',
            'scheduled_for' => ! empty($payload['scheduled_for']) ? $payload['scheduled_for'] : null,
            'created_by' => auth()->id(),
        ]);

        return (int) $row->id;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function runCampaign(int $campaignId): ?array
    {
        $campaign = BaleCampaign::query()->find($campaignId);
        if (! $campaign) {
            return null;
        }
        $chatIds = $this->resolveSegmentChatIds((string) $campaign->segment_key);
        $client = $this->makeClient();
        $delivered = 0;
        $failed = 0;
        $now = now();
        $text = (string) $campaign->message_template;

        foreach ($chatIds as $chatId) {
            $res = $client->sendMessage(['chat_id' => $chatId, 'text' => $text]);
            $ok = is_array($res) && ! empty($res['ok']);
            if ($ok) {
                $delivered++;
            } else {
                $failed++;
            }
            BaleCampaignDelivery::query()->create([
                'campaign_id' => $campaign->id,
                'chat_id' => $chatId,
                'variant' => (string) $campaign->variant,
                'status' => $ok ? 'delivered' : 'failed',
                'delivered_at' => $ok ? $now : null,
                'response_payload' => json_encode($res, JSON_UNESCAPED_UNICODE),
            ]);
        }

        $campaign->update([
            'status' => 'sent',
            'updated_at' => $now,
        ]);

        return [
            'total' => count($chatIds),
            'delivered' => $delivered,
            'failed' => $failed,
            'metrics' => $this->campaignMetrics($campaignId),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function campaignMetrics(int $campaignId): array
    {
        $data = [
            'delivered' => 0,
            'clicked' => 0,
            'replied' => 0,
            'converted' => 0,
        ];
        $rows = BaleCampaignDelivery::query()
            ->where('campaign_id', $campaignId)
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->get();
        foreach ($rows as $row) {
            $st = (string) $row->status;
            if (array_key_exists($st, $data)) {
                $data[$st] = (int) $row->cnt;
            }
        }

        return $data;
    }

    /**
     * @return list<string>
     */
    public function resolveSegmentChatIds(string $segmentKey): array
    {
        $q = BaleLead::query()->orderByDesc('id')->limit(2000);
        if ($segmentKey === 'hot-leads') {
            $q->whereIn('funnel_stage', ['hot', 'sales-ready']);
        } elseif ($segmentKey === 'past-buyers') {
            $q->where('score', '>=', 90);
        } elseif ($segmentKey === 'inactive-30d') {
            $q->whereRaw('last_event_at < DATE_SUB(NOW(), INTERVAL 30 DAY)');
        } elseif ($segmentKey === 'newcomer') {
            $q->where('funnel_stage', 'new');
        } else {
            $q->where('funnel_stage', 'new');
        }

        return $q->pluck('chat_id')->map(fn ($c) => (string) $c)->filter()->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function kpiDashboard(): array
    {
        $started = (int) BaleEvent::query()
            ->where('event_type', 'conversation_started')
            ->distinct()
            ->count('chat_id');
        $leadCount = (int) BaleLead::query()->count();
        $converted = (int) BaleLead::query()->whereNotNull('converted_customer_id')->where('converted_customer_id', '>', 0)->count();
        $salesReady = (int) BaleLead::query()->where('funnel_stage', 'sales-ready')->count();
        $deliveries = (int) BaleCampaignDelivery::query()->count();
        $clicked = (int) BaleCampaignDelivery::query()->where('status', 'clicked')->count();
        $replied = (int) BaleCampaignDelivery::query()->where('status', 'replied')->count();
        $leadRate = $started > 0 ? round(($leadCount / $started) * 100, 2) : 0;
        $convertRate = $leadCount > 0 ? round(($converted / $leadCount) * 100, 2) : 0;

        return [
            'start_to_lead_rate' => $leadRate,
            'lead_to_customer_rate' => $convertRate,
            'first_response_minutes' => 0,
            'retention_rate' => 0,
            'campaign_revenue_impact' => 0,
            'funnel_dropoff' => [
                'started_users' => $started,
                'leads' => $leadCount,
                'sales_ready' => $salesReady,
                'customers' => $converted,
            ],
            'campaign_metrics' => [
                'deliveries' => $deliveries,
                'clicked' => $clicked,
                'replied' => $replied,
            ],
        ];
    }

    /**
     * @return array{processed: int, failed: int}
     */
    public function processAutomationQueue(): array
    {
        return $this->automation->processDueQueue($this->makeClient());
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function log(string $level, string $type, array $context = []): void
    {
        BaleLog::query()->create([
            'level' => $level,
            'log_type' => $type,
            'context' => json_encode($context, JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function recordConversationStarted(string $chatId): void
    {
        BaleEvent::query()->create([
            'chat_id' => $chatId,
            'event_type' => 'conversation_started',
            'payload' => null,
        ]);
    }
}
