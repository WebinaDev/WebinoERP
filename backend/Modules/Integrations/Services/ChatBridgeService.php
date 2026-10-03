<?php

namespace Modules\Integrations\Services;

use Illuminate\Support\Facades\Http;
use Modules\Crm\Entities\CrmLead;
use Modules\Crm\Entities\CrmStatus;
use Modules\Integrations\Entities\ChatBridge;
use Modules\Integrations\Entities\ChatMessage;
use Modules\Projects\Entities\ProjectTask;

class ChatBridgeService
{
    public function send(ChatBridge $bridge, string $text, ?string $chatId): bool
    {
        $token = (string) $bridge->bot_token;
        $target = $chatId ?: (string) $bridge->channel_id;
        $ok = false;
        try {
            $ok = match ($bridge->provider) {
                'slack' => $this->sendSlack($token, $target, $text),
                'telegram' => $this->sendTelegram('https://api.telegram.org/bot'.$token.'/sendMessage', $target, $text),
                'bale' => $this->sendTelegram('https://tapi.bale.ai/bot'.$token.'/sendMessage', $target, $text),
                default => false,
            };
        } catch (\Throwable) {
            $ok = false;
        }

        ChatMessage::query()->create([
            'bridge_id' => $bridge->id,
            'direction' => 'out',
            'chat_id' => $target,
            'body' => $text,
            'payload' => ['ok' => $ok],
        ]);

        return $ok;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function ingest(ChatBridge $bridge, array $payload): array
    {
        if ($bridge->provider === 'slack' && ($payload['type'] ?? '') === 'url_verification') {
            return ['challenge' => (string) ($payload['challenge'] ?? '')];
        }

        [$text, $chatId, $externalId] = $this->extract($bridge->provider, $payload);
        if ($text === null) {
            return ['ok' => true, 'ignored' => true];
        }

        ChatMessage::query()->create([
            'bridge_id' => $bridge->id,
            'direction' => 'in',
            'external_id' => $externalId,
            'chat_id' => $chatId,
            'body' => $text,
            'payload' => $payload,
        ]);

        $reply = $bridge->inbound_commands ? $this->command($text) : null;
        if ($reply !== null && $chatId) {
            $this->send($bridge, $reply, $chatId);
        }

        return ['ok' => true, 'reply' => $reply];
    }

    private function sendSlack(string $token, string $channel, string $text): bool
    {
        if (str_starts_with($token, 'https://')) {
            return Http::timeout(15)->post($token, ['text' => $text])->successful();
        }
        if ($token === '' || $channel === '') {
            return false;
        }

        return Http::timeout(15)
            ->withToken($token)
            ->post('https://slack.com/api/chat.postMessage', ['channel' => $channel, 'text' => $text])
            ->successful();
    }

    private function sendTelegram(string $url, string $chatId, string $text): bool
    {
        if ($chatId === '' || ! str_contains($url, 'bot') || str_ends_with($url, 'bot/sendMessage')) {
            return false;
        }

        return Http::timeout(15)->post($url, [
            'chat_id' => $chatId,
            'text' => $text,
        ])->successful();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: ?string, 1: ?string, 2: ?string}
     */
    private function extract(string $provider, array $payload): array
    {
        if ($provider === 'slack') {
            $event = is_array($payload['event'] ?? null) ? $payload['event'] : [];
            if (($event['type'] ?? '') !== 'message' || isset($event['bot_id'])) {
                return [null, null, null];
            }

            return [
                isset($event['text']) ? (string) $event['text'] : null,
                isset($event['channel']) ? (string) $event['channel'] : null,
                isset($event['ts']) ? (string) $event['ts'] : null,
            ];
        }

        $message = is_array($payload['message'] ?? null) ? $payload['message'] : [];
        $chat = is_array($message['chat'] ?? null) ? $message['chat'] : [];

        return [
            isset($message['text']) ? (string) $message['text'] : null,
            isset($chat['id']) ? (string) $chat['id'] : null,
            isset($message['message_id']) ? (string) $message['message_id'] : null,
        ];
    }

    private function command(string $text): ?string
    {
        $text = trim($text);
        if ($text === '/help') {
            return "Commands:\n/task title\n/lead First Last | 09120000000\n/help";
        }
        if (str_starts_with($text, '/task ')) {
            $title = trim(substr($text, 6));
            if ($title === '') {
                return 'Title is required.';
            }
            $task = ProjectTask::query()->create([
                'title' => $title,
                'status' => 'open',
                'priority' => 'normal',
            ]);

            return 'Task #'.$task->id.' created.';
        }
        if (str_starts_with($text, '/lead ')) {
            $rest = trim(substr($text, 6));
            [$name, $mobile] = array_pad(explode('|', $rest, 2), 2, '');
            $parts = preg_split('/\s+/', trim($name)) ?: [];
            $first = (string) ($parts[0] ?? 'Lead');
            $last = (string) ($parts[1] ?? 'Inbound');
            $statusId = CrmStatus::query()->orderBy('sort_order')->value('id')
                ?: CrmStatus::query()->create(['name' => 'New', 'color' => '#64748b', 'sort_order' => 0, 'is_active' => true])->id;
            $lead = CrmLead::query()->create([
                'topic' => 'Inbound chat',
                'first_name' => $first,
                'last_name' => $last,
                'mobile' => trim($mobile) !== '' ? trim($mobile) : '-',
                'status_id' => $statusId,
            ]);

            return 'Lead #'.$lead->id.' created.';
        }

        return null;
    }
}
