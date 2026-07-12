<?php

namespace Modules\Integrations\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Modules\Integrations\Services\Bale\BaleSettingsStore;

/**
 * POST /api/webinocrm/v1/bale/webhook — به‌روزرسانی بله (بدون وردپرس).
 */
class BaleWebhookHandler
{
    public function __construct(
        private BaleBusinessService $bale,
        private BaleSettingsStore $settings,
    ) {}

    /**
     * @return array{ok: bool, duplicate?: bool, error?: string, status: int}
     */
    public function handle(Request $request): array
    {
        $incoming = $this->readSecretToken($request);
        $expected = $this->settings->webhookSecret();
        if ($expected !== '' && (! is_string($incoming) || ! hash_equals($expected, trim($incoming)))) {
            $this->bale->log('error', 'webhook_secret_mismatch', []);

            return ['ok' => false, 'error' => 'unauthorized', 'status' => 401];
        }

        $raw = $request->getContent();
        $data = json_decode($raw, true);
        if (! is_array($data)) {
            $this->bale->log('error', 'webhook_invalid_payload', []);

            return ['ok' => false, 'error' => 'invalid_payload', 'status' => 400];
        }

        $updateId = isset($data['update_id']) ? (int) $data['update_id'] : 0;
        if ($updateId > 0) {
            $key = 'bale_seen_update_'.$updateId;
            if (! Cache::add($key, 1, now()->addDay())) {
                $this->bale->log('info', 'webhook_duplicate_update', ['update_id' => $updateId]);

                return ['ok' => true, 'duplicate' => true, 'status' => 200];
            }
        }

        try {
            $this->dispatch($data);
        } catch (\Throwable $e) {
            $this->bale->log('error', 'webhook_dispatch_exception', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return ['ok' => false, 'error' => 'dispatch_failed', 'status' => 500];
        }

        return ['ok' => true, 'status' => 200];
    }

    private function readSecretToken(Request $request): string
    {
        $h = $request->header('X-Telegram-Bot-Api-Secret-Token');
        if (is_string($h) && trim($h) !== '') {
            return trim($h);
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function dispatch(array $data): void
    {
        if (isset($data['message']) && is_array($data['message'])) {
            $this->onMessage($data['message']);

            return;
        }
        if (isset($data['callback_query']) && is_array($data['callback_query'])) {
            $this->onCallback($data['callback_query']);
        }
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function onMessage(array $message): void
    {
        $chatId = (string) ($message['chat']['id'] ?? '');
        if ($chatId === '') {
            return;
        }

        $text = trim((string) ($message['text'] ?? ''));
        if ($text !== '') {
            $this->bale->automation()->ingestEvent($chatId, 'reply', ['text' => mb_substr($text, 0, 180)]);
        }

        if ($text === '/start') {
            $this->bale->recordConversationStarted($chatId);
            $this->bale->automation()->ingestEvent($chatId, 'start', []);
            $merged = $this->settings->merged();
            $welcome = (string) ($merged['welcome_text'] ?? '');
            $hint = (string) ($merged['start_hint_text'] ?? '');
            $full = trim($welcome."\n\n".$hint);
            if ($full !== '') {
                $this->sendText($chatId, $full);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $callback
     */
    private function onCallback(array $callback): void
    {
        $chatId = (string) ($callback['message']['chat']['id'] ?? '');
        $data = (string) ($callback['data'] ?? '');
        $callbackId = (string) ($callback['id'] ?? '');
        if ($chatId === '' || $data === '') {
            return;
        }

        $merged = $this->settings->merged();
        $client = $this->bale->makeClient();

        if ($callbackId !== '' && $client->hasToken()) {
            $client->answerCallbackQuery(['callback_query_id' => $callbackId]);
        }

        if (str_starts_with($data, 'wbb:plan:') || str_contains($data, 'plan')) {
            $this->bale->automation()->ingestEvent($chatId, 'plan_click', ['data' => $data]);
            $cta = (string) ($merged['cta_order_text'] ?? 'ثبت درخواست');
            $this->sendText($chatId, $cta);

            return;
        }

        if ($data === 'wbb:menu:features' || str_starts_with($data, 'wbb:features')) {
            $intro = (string) ($merged['features_intro_text'] ?? '');
            $this->sendText($chatId, $intro !== '' ? $intro : 'امکانات ربات فروش وبینا');

            return;
        }

        if ($data === 'wbb:menu:faq' || str_starts_with($data, 'wbb:faq')) {
            $intro = (string) ($merged['faq_intro_text'] ?? 'سوالات پرتکرار');
            $items = $merged['faq_items'] ?? [];
            $lines = [$intro];
            if (is_array($items)) {
                foreach ($items as $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $q = (string) ($item['question'] ?? '');
                    $a = (string) ($item['answer'] ?? '');
                    if ($q !== '') {
                        $lines[] = "• {$q}\n  {$a}";
                    }
                }
            }
            $this->sendText($chatId, implode("\n\n", $lines));

            return;
        }

        if ($data === 'wbb:support' || str_starts_with($data, 'wbb:menu:support')) {
            $this->bale->automation()->recordTimelineEvent($chatId, 'support_opened', []);
            $intro = (string) ($merged['support_intro_text'] ?? '');
            $cta = (string) ($merged['support_cta_text'] ?? '');
            $this->sendText($chatId, trim($intro."\n\n".$cta));

            return;
        }

        if (str_starts_with($data, 'wbb:support:item:')) {
            $this->bale->automation()->recordTimelineEvent($chatId, 'support_item_clicked', ['data' => $data]);
            $index = (int) substr($data, strlen('wbb:support:item:'));
            $items = $merged['support_items'] ?? [];
            if (is_array($items) && isset($items[$index]) && is_array($items[$index])) {
                $item = $items[$index];
                $title = (string) ($item['title'] ?? '');
                $desc = (string) ($item['description'] ?? '');
                $action = (string) ($item['action_value'] ?? '');
                $this->sendText($chatId, trim("{$title}\n{$desc}\n{$action}"));
            }

            return;
        }

        if (str_contains($data, 'support')) {
            $this->bale->automation()->recordTimelineEvent($chatId, 'support_opened', []);
        }
    }

    private function sendText(string $chatId, string $text): void
    {
        if ($text === '') {
            return;
        }
        $client = $this->bale->makeClient();
        if ($client->hasToken()) {
            $client->sendMessage(['chat_id' => $chatId, 'text' => $text]);
        }
    }
}
