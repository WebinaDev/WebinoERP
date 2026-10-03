<?php

namespace Modules\Integrations\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Entities\CoreNotification;
use Modules\Integrations\Entities\CalendarAccount;
use Modules\Integrations\Entities\ChannelDelivery;
use Modules\Integrations\Entities\ChatBridge;
use Modules\Integrations\Entities\ChatMessage;
use Modules\Integrations\Entities\EmailAccount;
use Modules\Integrations\Entities\EmailMessage;
use Modules\Integrations\Entities\InboundEvent;
use Modules\Integrations\Entities\IntegrationSetting;
use Modules\Integrations\Entities\SmsRule;
use Modules\Integrations\Jobs\ProcessInboundChatJob;
use Modules\Integrations\Services\CalendarSyncService;
use Modules\Integrations\Services\ChatBridgeService;
use Modules\Integrations\Services\MailboxService;
use Modules\Integrations\Services\NotificationFanout;

class LiveConnectController extends Controller
{
    public function __construct(
        private readonly CalendarSyncService $calendars,
        private readonly ChatBridgeService $chat,
        private readonly MailboxService $mail,
        private readonly NotificationFanout $fanout,
    ) {}

    public function connectUrl(string $provider): JsonResponse
    {
        abort_unless(in_array($provider, ['google', 'outlook'], true), 404);

        return response()->json(['data' => ['url' => $this->calendars->authorizationUrl($provider, (int) request()->user()->id)]]);
    }

    public function callback(Request $request, string $provider): JsonResponse
    {
        $data = $request->validate(['code' => 'required|string', 'state' => 'required|string']);
        $account = $this->calendars->handleCallback($provider, $data['code'], $data['state']);

        return response()->json(['data' => $this->calendarResource($account)]);
    }

    public function storeCalendar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'provider' => 'required|in:google,outlook',
            'access_token' => 'required|string',
            'refresh_token' => 'nullable|string',
            'expires_in' => 'nullable|integer',
            'email' => 'nullable|email',
            'calendar_id' => 'nullable|string',
        ]);
        $account = $this->calendars->storeAccount((int) $request->user()->id, $data['provider'], $data, $data['calendar_id'] ?? 'primary');

        return response()->json(['data' => $this->calendarResource($account)], 201);
    }

    public function calendarsIndex(Request $request): JsonResponse
    {
        $rows = CalendarAccount::query()->where('user_id', $request->user()->id)->orderByDesc('id')->get()
            ->map(fn (CalendarAccount $row) => $this->calendarResource($row));

        return response()->json(['data' => $rows]);
    }

    public function syncCalendar(int $id): JsonResponse
    {
        $account = CalendarAccount::query()->where('user_id', request()->user()->id)->findOrFail($id);
        $stats = $this->calendars->sync($account);

        return response()->json(['data' => $stats]);
    }

    public function googleWebhook(Request $request): JsonResponse
    {
        $channel = (string) $request->header('X-Goog-Channel-Id', '');
        $synced = $channel !== '' && $this->calendars->syncByChannel($channel);

        return response()->json(['ok' => true, 'synced' => $synced]);
    }

    public function outlookWebhook(Request $request)
    {
        if ($request->filled('validationToken')) {
            return response((string) $request->query('validationToken'), 200)->header('Content-Type', 'text/plain');
        }
        $count = $this->calendars->syncOutlookNotifications($request->all());

        return response()->json(['ok' => true, 'synced' => $count]);
    }

    public function bridgesIndex(): JsonResponse
    {
        return response()->json(['data' => ChatBridge::query()->orderByDesc('id')->get()->map(fn (ChatBridge $b) => $this->bridgeResource($b))]);
    }

    public function storeBridge(Request $request): JsonResponse
    {
        $data = $request->validate([
            'provider' => 'required|in:slack,bale,telegram',
            'name' => 'required|string|max:120',
            'bot_token' => 'nullable|string',
            'webhook_secret' => 'nullable|string',
            'channel_id' => 'nullable|string',
            'inbound_commands' => 'nullable|boolean',
            'enabled' => 'nullable|boolean',
        ]);
        $bridge = ChatBridge::query()->create($data);

        return response()->json(['data' => $this->bridgeResource($bridge)], 201);
    }

    public function bridgeWebhook(Request $request, string $provider)
    {
        $bridge = ChatBridge::query()->where('provider', $provider)->where('enabled', true)->orderByDesc('id')->first();
        if (! $bridge) {
            return response()->json(['ok' => false], 404);
        }
        if (! $this->webhookAuthorized($request, $bridge)) {
            return response()->json(['ok' => false], 401);
        }
        $payload = $request->all();
        if ($bridge->provider === 'slack' && ($payload['type'] ?? '') === 'url_verification') {
            return response()->json(['challenge' => (string) ($payload['challenge'] ?? '')]);
        }
        $event = InboundEvent::query()->create([
            'provider' => $provider,
            'bridge_id' => $bridge->id,
            'external_id' => $this->webhookExternalId($provider, $payload),
            'payload' => $payload,
            'status' => 'queued',
            'available_at' => now(),
        ]);
        ProcessInboundChatJob::dispatch($event->id);

        return response()->json(['ok' => true, 'queued' => true, 'id' => $event->id]);
    }

    public function bridgeMessages(int $id): JsonResponse
    {
        $rows = ChatMessage::query()->where('bridge_id', $id)->orderByDesc('id')->limit(100)->get();

        return response()->json(['data' => $rows]);
    }

    public function mailboxIndex(Request $request): JsonResponse
    {
        return response()->json(['data' => EmailAccount::query()->where('user_id', $request->user()->id)->orderByDesc('id')->get()->map(fn (EmailAccount $a) => [
            'id' => $a->id,
            'provider' => $a->provider,
            'email' => $a->email,
            'imap_host' => $a->imap_host,
        ])]);
    }

    public function storeMailbox(Request $request): JsonResponse
    {
        $data = $request->validate([
            'provider' => 'required|in:google,microsoft,imap',
            'email' => 'required|email',
            'access_token' => 'nullable|string',
            'refresh_token' => 'nullable|string',
            'imap_host' => 'nullable|string',
            'imap_port' => 'nullable|integer',
            'smtp_host' => 'nullable|string',
            'smtp_port' => 'nullable|integer',
            'username' => 'nullable|string',
            'password' => 'nullable|string',
        ]);
        $data['user_id'] = $request->user()->id;
        $account = EmailAccount::query()->create($data);

        return response()->json(['data' => ['id' => $account->id, 'email' => $account->email, 'provider' => $account->provider]], 201);
    }

    public function searchMail(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => 'nullable|string|max:200',
            'account_id' => 'nullable|integer',
            'include_spam' => 'nullable|boolean',
        ]);
        $ids = $request->filled('account_id') ? [(int) $data['account_id']] : null;
        $rows = $this->mail->search((int) $request->user()->id, (string) ($data['q'] ?? ''), $ids, $request->boolean('include_spam'));

        return response()->json(['data' => $rows]);
    }

    public function syncMailbox(int $id): JsonResponse
    {
        $account = EmailAccount::query()->where('user_id', request()->user()->id)->findOrFail($id);

        return response()->json(['data' => $this->mail->sync($account)]);
    }

    public function importMail(Request $request, int $id): JsonResponse
    {
        $account = EmailAccount::query()->where('user_id', $request->user()->id)->findOrFail($id);
        $data = $request->validate([
            'external_id' => 'nullable|string',
            'thread_key' => 'nullable|string',
            'from_email' => 'nullable|email',
            'to_email' => 'nullable|email',
            'subject' => 'nullable|string',
            'body' => 'nullable|string',
            'direction' => 'nullable|in:in,out',
            'attachments' => 'nullable|array',
        ]);
        $message = $this->mail->import($account, $data);

        return response()->json(['data' => $message], 201);
    }

    public function threads(int $id): JsonResponse
    {
        $account = EmailAccount::query()->where('user_id', request()->user()->id)->findOrFail($id);
        $messages = EmailMessage::query()->where('account_id', $account->id)
            ->when(! request()->boolean('include_spam'), fn ($q) => $q->where('is_spam', false))
            ->orderByDesc('id')->limit(200)->get();
        $threads = $messages->groupBy(fn (EmailMessage $m) => $m->thread_key ?: 'solo-'.$m->id)->map(function ($group, $key) {
            return [
                'thread_key' => $key,
                'subject' => $group->first()->subject,
                'messages' => $group->values(),
            ];
        })->values();

        return response()->json(['data' => $threads]);
    }

    public function sendMail(Request $request, int $id): JsonResponse
    {
        $account = EmailAccount::query()->where('user_id', $request->user()->id)->findOrFail($id);
        $data = $request->validate([
            'to' => 'required|email',
            'subject' => 'required|string|max:200',
            'body' => 'required|string',
            'thread_key' => 'nullable|string',
        ]);
        $message = $this->mail->send($account, $data);

        return response()->json(['data' => $message], 201);
    }

    public function linkMail(Request $request, int $id): JsonResponse
    {
        $message = EmailMessage::query()->findOrFail($id);
        $data = $request->validate(['crm_account_id' => 'nullable|integer']);
        $activity = $this->mail->attachActivity($message, $data['crm_account_id'] ?? null);

        return response()->json(['data' => ['activity_id' => $activity->id, 'message_id' => $message->id]]);
    }

    public function feed(Request $request): JsonResponse
    {
        $notes = CoreNotification::query()->where('user_id', $request->user()->id)->orderByDesc('id')->limit(50)->get();
        $deliveries = ChannelDelivery::query()->where('user_id', $request->user()->id)->orderByDesc('id')->limit(50)->get();

        return response()->json(['data' => ['notifications' => $notes, 'deliveries' => $deliveries]]);
    }

    public function dispatchNote(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:160',
            'body' => 'nullable|string',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'event_key' => 'nullable|string|max:80',
            'user_id' => 'nullable|integer',
            'phone' => 'nullable|string',
            'channels' => 'nullable|array',
        ]);
        $data['user_id'] = $data['user_id'] ?? $request->user()->id;

        return response()->json(['data' => $this->fanout->dispatch($data)]);
    }

    public function smsPolicy(): JsonResponse
    {
        return response()->json(['data' => [
            'mode' => IntegrationSetting::getString('sms_policy', 'mode', 'highest_only'),
            'floor' => IntegrationSetting::getString('sms_policy', 'floor', 'high'),
            'rules' => SmsRule::query()->orderBy('id')->get(),
        ]]);
    }

    public function saveSmsPolicy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mode' => 'required|in:off,highest_only,matched',
            'floor' => 'required|in:low,normal,high,urgent',
            'rules' => 'nullable|array',
            'rules.*.name' => 'required_with:rules|string',
            'rules.*.event_key' => 'nullable|string',
            'rules.*.min_priority' => 'nullable|in:low,normal,high,urgent',
            'rules.*.enabled' => 'nullable|boolean',
        ]);
        IntegrationSetting::putString('sms_policy', 'mode', $data['mode']);
        IntegrationSetting::putString('sms_policy', 'floor', $data['floor']);
        if (array_key_exists('rules', $data)) {
            SmsRule::query()->delete();
            foreach ($data['rules'] as $rule) {
                SmsRule::query()->create([
                    'name' => $rule['name'],
                    'event_key' => $rule['event_key'] ?? '*',
                    'min_priority' => $rule['min_priority'] ?? $data['floor'],
                    'enabled' => $rule['enabled'] ?? true,
                ]);
            }
        }

        return $this->smsPolicy();
    }

    private function calendarResource(CalendarAccount $account): array
    {
        return [
            'id' => $account->id,
            'provider' => $account->provider,
            'email' => $account->email,
            'calendar_id' => $account->calendar_id,
            'status' => $account->status,
            'refresh_attempts' => $account->refresh_attempts,
            'last_refresh_error' => $account->last_refresh_error,
            'last_synced_at' => optional($account->last_synced_at)?->toIso8601String(),
            'webhook_channel_id' => $account->webhook_channel_id,
        ];
    }

    private function bridgeResource(ChatBridge $bridge): array
    {
        return [
            'id' => $bridge->id,
            'provider' => $bridge->provider,
            'name' => $bridge->name,
            'channel_id' => $bridge->channel_id,
            'inbound_commands' => $bridge->inbound_commands,
            'enabled' => $bridge->enabled,
            'has_token' => filled($bridge->bot_token),
            'webhook_url' => url('/api/v1/integrations/bridges/webhook/'.$bridge->provider),
        ];
    }

    private function webhookAuthorized(Request $request, ChatBridge $bridge): bool
    {
        $secret = (string) $bridge->webhook_secret;
        if ($secret === '') {
            return true;
        }
        $header = (string) $request->header('X-Webhook-Secret', $request->header('X-Telegram-Bot-Api-Secret-Token', ''));
        if ($header !== '' && hash_equals($secret, $header)) {
            return true;
        }
        $slackSig = (string) $request->header('X-Slack-Signature', '');
        $timestamp = (string) $request->header('X-Slack-Request-Timestamp', '');
        if ($slackSig !== '' && $timestamp !== '' && abs(time() - (int) $timestamp) <= 300) {
            $base = 'v0:'.$timestamp.':'.$request->getContent();
            $expect = 'v0='.hash_hmac('sha256', $base, $secret);

            return hash_equals($expect, $slackSig);
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function webhookExternalId(string $provider, array $payload): ?string
    {
        if ($provider === 'slack') {
            $event = is_array($payload['event'] ?? null) ? $payload['event'] : [];

            return isset($event['ts']) ? (string) $event['ts'] : null;
        }
        $message = is_array($payload['message'] ?? null) ? $payload['message'] : [];

        return isset($message['message_id']) ? (string) $message['message_id'] : null;
    }
}
