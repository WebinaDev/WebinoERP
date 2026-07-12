<?php

namespace Modules\Core\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Modules\Core\Entities\CoreAutomationRule;
use Modules\Core\Entities\CoreAutomationRun;
use Modules\Core\Entities\CoreNotification;

class AutomationEngine
{
    /**
     * @param  array<string,mixed>  $event
     */
    public function dispatch(string $trigger, array $event): array
    {
        $rules = CoreAutomationRule::query()
            ->where('trigger', $trigger)
            ->where('is_active', true)
            ->orderBy('priority')
            ->get();

        $results = [];
        foreach ($rules as $rule) {
            $results[] = $this->runRule($rule, $event);
        }

        return $results;
    }

    /**
     * @param  array<string,mixed>  $event
     * @return array<string,mixed>
     */
    public function runRule(CoreAutomationRule $rule, array $event): array
    {
        $run = CoreAutomationRun::query()->create([
            'rule_id' => $rule->id,
            'status' => 'running',
            'event_payload' => $event,
            'started_at' => now(),
        ]);

        try {
            if (! $this->matchConditions($rule->conditions ?? [], $event)) {
                $run->update([
                    'status' => 'skipped',
                    'result_payload' => ['reason' => 'condition_mismatch'],
                    'finished_at' => now(),
                ]);

                return ['rule_id' => $rule->id, 'status' => 'skipped'];
            }

            $executed = [];
            foreach (($rule->actions ?? []) as $action) {
                $executed[] = $this->executeAction($action, $event);
            }

            $run->update([
                'status' => 'success',
                'result_payload' => ['actions' => $executed],
                'finished_at' => now(),
            ]);

            return ['rule_id' => $rule->id, 'status' => 'success', 'actions' => $executed];
        } catch (\Throwable $e) {
            $run->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
                'finished_at' => now(),
            ]);

            return ['rule_id' => $rule->id, 'status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    /**
     * @param  array<string,mixed>  $conditions
     * @param  array<string,mixed>  $event
     */
    private function matchConditions(array $conditions, array $event): bool
    {
        if ($conditions === []) {
            return true;
        }

        foreach ($conditions as $key => $expected) {
            $actual = Arr::get($event, $key);
            if ($actual != $expected) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string,mixed>  $action
     * @param  array<string,mixed>  $event
     * @return array<string,mixed>
     */
    private function executeAction(array $action, array $event): array
    {
        $type = (string) ($action['type'] ?? '');
        $payload = (array) ($action['payload'] ?? []);

        return match ($type) {
            'notify' => $this->executeNotify($payload, $event),
            'send-telegram' => $this->executeTelegram($payload, $event),
            default => ['type' => $type, 'status' => 'ignored'],
        };
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $event
     * @return array<string,mixed>
     */
    private function executeTelegram(array $payload, array $event): array
    {
        $token = config('integrations.telegram.token');
        $chatId = (string) ($payload['chat_id'] ?? '');
        $text = (string) ($payload['text'] ?? Arr::get($event, 'message', ''));
        if (! $token || $chatId === '' || $text === '') {
            return ['type' => 'send-telegram', 'status' => 'skipped', 'reason' => 'missing_token_chat_or_text'];
        }
        $url = 'https://api.telegram.org/bot'.$token.'/sendMessage';
        $res = Http::asJson()->post($url, [
            'chat_id' => $chatId,
            'text' => $text,
        ]);
        if (! $res->successful()) {
            return ['type' => 'send-telegram', 'status' => 'failed', 'body' => $res->body()];
        }

        return ['type' => 'send-telegram', 'status' => 'ok'];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $event
     * @return array<string,mixed>
     */
    private function executeNotify(array $payload, array $event): array
    {
        $userId = (int) ($payload['user_id'] ?? Arr::get($event, 'user_id', 0));
        if ($userId <= 0) {
            return ['type' => 'notify', 'status' => 'skipped', 'reason' => 'missing_user_id'];
        }

        CoreNotification::query()->create([
            'user_id' => $userId,
            'type' => (string) ($payload['notification_type'] ?? 'automation'),
            'data' => [
                'title' => (string) ($payload['title'] ?? 'Automation notification'),
                'body' => (string) ($payload['body'] ?? ''),
                'event' => $event,
            ],
            'is_read' => false,
        ]);

        return ['type' => 'notify', 'status' => 'ok', 'user_id' => $userId];
    }
}
