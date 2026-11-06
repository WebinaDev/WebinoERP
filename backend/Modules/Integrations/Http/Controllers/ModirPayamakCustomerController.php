<?php

namespace Modules\Integrations\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Modules\Integrations\Entities\ModirPayamakOrder;
use Modules\Integrations\Entities\ModirPayamakPackage;
use Modules\Integrations\Entities\PaymentIntent;
use Modules\Integrations\Services\ModirPayamakEdgeClient;
use Modules\Integrations\Services\ModirPayamakManager;
use Modules\Integrations\Services\Payments\PaymentException;
use Modules\Integrations\Services\Payments\PaymentOrchestrator;

class ModirPayamakCustomerController extends Controller
{
    public function __construct(
        private ModirPayamakEdgeClient $edge,
        private ModirPayamakManager $manager,
        private PaymentOrchestrator $payments,
    ) {}

    private function domain(Request $request): string
    {
        $domain = (string) ($request->input('domain') ?? $request->query('domain', ''));
        $this->manager->assertLicensedDomain($domain);
        $mock = (bool) config('integrations.modirpayamak.mock', false);
        if (! $mock && ! $this->edge->isConfigured()) {
            abort(503, __('messages.modirpayamak_is_not_configured'));
        }

        return $this->manager->normalizeDomain($domain);
    }

    public function account(Request $request): JsonResponse
    {
        $domain = $this->domain($request);
        $account = $this->manager->getOrCreateAccount($domain);
        // Upstream credit is informational only; cache it and never let a slow/failed
        // ippanel call fail the balance endpoint (tenant balance comes from our DB).
        $credit = \Illuminate\Support\Facades\Cache::remember('modirpayamak:edge_credit', 120, function () {
            $res = $this->edge->myCredit();

            return $res['ok'] ? $res['data'] : null;
        });

        return response()->json(['data' => [
            'account' => $this->manager->formatAccountPublic($account),
            'edge_credit' => $credit,
        ]]);
    }

    public function packages(): JsonResponse
    {
        $packages = ModirPayamakPackage::query()->where('is_active', true)->orderBy('sort_order')->get();

        return response()->json(['data' => $packages]);
    }

    public function topupInit(Request $request): JsonResponse
    {
        $data = $request->validate([
            'domain' => 'required|string|max:255',
            'package_id' => 'required|exists:modirpayamak_packages,id',
            'callback_url' => 'nullable|url',
            'return_url' => 'nullable|url',
            'mode' => 'nullable|string|in:cash,installment',
            'gateway' => 'nullable|string|max:32',
            'mobile' => 'nullable|string|max:20',
        ]);
        $domain = $this->domain($request);
        $package = ModirPayamakPackage::query()->findOrFail($data['package_id']);
        try {
            $intent = $this->payments->start([
                'payable_type' => 'sms_credit',
                'payable_id' => (string) $package->id,
                'domain' => $domain,
                'mode' => $data['mode'] ?? 'cash',
                'gateway' => $data['gateway'] ?? null,
                'return_url' => $data['return_url'] ?? $data['callback_url'] ?? null,
                'mobile' => $data['mobile'] ?? null,
            ], $request->user()?->id);
            $order = ModirPayamakOrder::query()->find($intent->payable_id);
            if ($order) {
                $order->update(['authority' => $intent->authority, 'user_id' => $request->user()?->id]);
            }

            return response()->json([
                'data' => array_merge($intent->toApiArray(), [
                    'order_id' => $order?->id,
                    'authority' => $intent->authority,
                    'redirect_url' => $intent->redirect_url,
                    'amount' => $package->amount,
                ]),
            ]);
        } catch (PaymentException $e) {
            $fallback = ($data['gateway'] ?? null) === null
                && ($data['mode'] ?? 'cash') === 'cash'
                && in_array($e->errorCode, ['no_gateway', 'gateway_disabled', 'mode_not_allowed'], true);
            if (! $fallback) {
                return response()->json(['message' => $e->getMessage(), 'error' => $e->errorCode], $e->status);
            }
        }

        $authority = 'A'.strtoupper(bin2hex(random_bytes(16)));
        $order = ModirPayamakOrder::query()
            ->where('domain', $domain)
            ->where('package_id', $package->id)
            ->where('status', 'pending')
            ->latest()
            ->first();
        if ($order) {
            $order->update([
                'authority' => $authority,
                'user_id' => $request->user()?->id,
            ]);
        } else {
            $order = ModirPayamakOrder::create([
                'domain' => $domain,
                'package_id' => $package->id,
                'amount' => $package->amount,
                'authority' => $authority,
                'status' => 'pending',
                'user_id' => $request->user()?->id,
            ]);
        }
        Cache::put('modirpayamak:topup:'.$authority, ['order_id' => $order->id], now()->addHours(2));

        return response()->json([
            'data' => [
                'order_id' => $order->id,
                'authority' => $authority,
                'redirect_url' => ($data['callback_url'] ?? url('/payment/callback')).'?Authority='.$authority,
                'amount' => $package->amount,
            ],
        ]);
    }

    public function topupVerify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'domain' => 'required|string|max:255',
            'authority' => 'required|string',
            'status' => 'nullable|string',
        ]);
        $domain = $this->domain($request);
        $intent = PaymentIntent::query()->where('authority', $data['authority'])->first();
        if ($intent && $intent->payable_type === 'sms_order') {
            $response = $this->payments->finish($intent, $request->all(), true);
            $order = ModirPayamakOrder::query()->find($intent->payable_id);
            if ($response->status() >= 400) {
                return response()->json($response->getData(true), $response->status());
            }
            $account = $this->manager->getOrCreateAccount($domain);

            return response()->json(['data' => ['order' => $order?->fresh(), 'account' => $account, 'payment' => $intent->fresh()->toApiArray()], 'message' => __('messages.topup_verified')]);
        }
        $cached = Cache::get('modirpayamak:topup:'.$data['authority']);
        $order = ModirPayamakOrder::query()->where('authority', $data['authority'])->where('domain', $domain)->firstOrFail();
        if (($data['status'] ?? 'OK') !== 'OK') {
            $order->update(['status' => 'failed']);

            return response()->json(['message' => __('messages.payment_failed')], 422);
        }
        $order->update(['status' => 'paid', 'ref_id' => $data['authority']]);
        $package = $order->package;
        $units = $package ? (float) $package->amount / $this->manager->pricePerUnit() : (float) $order->amount / $this->manager->pricePerUnit();
        $account = $this->manager->credit($domain, $units, 'topup', (string) $order->id, ['package_id' => $order->package_id]);
        Cache::forget('modirpayamak:topup:'.$data['authority']);

        return response()->json(['data' => ['order' => $order->fresh(), 'account' => $account], 'message' => __('messages.topup_verified')]);
    }

    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'domain' => 'required|string|max:255',
            'message' => 'nullable|string',
            'phone' => 'nullable|string',
            'recipients' => 'nullable|array',
            'from_number' => 'nullable|string',
            'sending_type' => 'nullable|string',
            'code' => 'nullable|string',
            'params' => 'nullable|array',
            'send_time' => 'nullable',
        ]);
        $domain = $this->domain($request);
        $recipients = $this->collectRecipients($data);
        if ($recipients === []) {
            return response()->json(['message' => 'recipients required', 'ok' => false], 422);
        }
        $type = strtolower((string) ($data['sending_type'] ?? ''));
        $code = trim((string) ($data['code'] ?? ''));
        $isPattern = $type === 'pattern' || $code !== '';
        $message = (string) ($data['message'] ?? '');
        if (! $isPattern && trim($message) === '') {
            return response()->json(['message' => 'message required', 'ok' => false], 422);
        }
        if ($isPattern && $code === '') {
            return response()->json(['message' => 'pattern code required', 'ok' => false], 422);
        }

        $from = (string) ($data['from_number'] ?? $this->edge->defaultFrom());
        $price = $this->manager->pricePerUnit();
        $cost = count($recipients) * $price;
        $account = $this->manager->getOrCreateAccount($domain);
        if ((float) $account->balance < $cost) {
            return response()->json([
                'message' => __('messages.insufficient_modirpayamak_balance'),
                'ok' => false,
                'reason' => 'insufficient_balance',
            ], 402);
        }

        if ($isPattern) {
            $params = is_array($data['params'] ?? null) ? $data['params'] : [];
            $result = $this->edge->sendPattern($from, $code, $recipients, $params);
            if ($message === '') {
                $message = $code;
            }
            $sendingType = 'pattern';
        } else {
            $result = $this->edge->sendWebservice($from, $message, $recipients, $this->formatSendTime($data['send_time'] ?? null));
            $sendingType = 'webservice';
            $code = '';
        }
        if (! $result['ok']) {
            return response()->json(['message' => $result['message'] ?: 'Send failed', 'meta' => $result['meta'] ?? [], 'ok' => false], 422);
        }

        try {
            $account = $this->manager->debit($domain, $cost, 'send', null, [
                'recipients' => count($recipients),
                'type' => $sendingType,
            ]);
        } catch (\Throwable) {
            $account = $this->manager->getOrCreateAccount($domain);
        }
        $this->manager->recordMessage(
            $domain,
            $sendingType,
            $message,
            $recipients,
            $from,
            'sent',
            $cost,
            $code !== '' ? $code : null,
            ['edge' => $result['data'] ?? null],
        );

        return response()->json(['data' => [
            'ok' => true,
            'edge' => $result['data'],
            'account' => $this->manager->formatAccountPublic($account),
            'cost' => $cost,
        ]]);
    }

    public function sendPeerToPeer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'domain' => 'required|string|max:255',
            'groups' => 'required|array|min:1',
            'from_number' => 'nullable|string',
        ]);
        $domain = $this->domain($request);
        $from = (string) ($data['from_number'] ?? $this->edge->defaultFrom());
        $recipients = [];
        $preview = '';
        foreach ($data['groups'] as $group) {
            if (! is_array($group)) {
                continue;
            }
            if ($preview === '' && isset($group['message'])) {
                $preview = (string) $group['message'];
            }
            foreach ((array) ($group['recipients'] ?? []) as $phone) {
                if (is_scalar($phone) && trim((string) $phone) !== '') {
                    $recipients[] = trim((string) $phone);
                }
            }
        }
        $recipients = array_values(array_unique($recipients));
        $cost = max(1, count($recipients)) * $this->manager->pricePerUnit();
        $account = $this->manager->getOrCreateAccount($domain);
        if ((float) $account->balance < $cost) {
            return response()->json([
                'message' => __('messages.insufficient_modirpayamak_balance'),
                'ok' => false,
                'reason' => 'insufficient_balance',
            ], 402);
        }
        $result = $this->edge->sendPeerToPeer($data['groups'], $from);
        if (! $result['ok']) {
            return response()->json(['message' => $result['message'] ?: 'Send failed', 'ok' => false], 422);
        }
        try {
            $account = $this->manager->debit($domain, $cost, 'send', null, [
                'recipients' => count($recipients),
                'type' => 'peer_to_peer',
            ]);
        } catch (\Throwable) {
            $account = $this->manager->getOrCreateAccount($domain);
        }
        $this->manager->recordMessage($domain, 'peer_to_peer', $preview, $recipients, $from, 'sent', $cost, null, [
            'edge' => $result['data'] ?? null,
        ]);

        return response()->json(['data' => [
            'ok' => true,
            'edge' => $result['data'],
            'account' => $this->manager->formatAccountPublic($account),
            'cost' => $cost,
        ]]);
    }

    public function calculatePrice(Request $request): JsonResponse
    {
        $this->domain($request);
        $payload = $request->validate([
            'domain' => 'required|string|max:255',
            'sending_type' => 'required|string',
            'from_number' => 'nullable|string',
            'message' => 'nullable|string',
            'params' => 'nullable|array',
        ]);
        $result = $this->edge->calculatePrice($payload);

        return response()->json(['data' => $result['data'], 'meta' => $result['meta']], $result['ok'] ? 200 : 422);
    }

    public function reportsOutbox(Request $request): JsonResponse
    {
        $domain = $this->domain($request);
        $limit = max(1, (int) $request->query('limit', 20));
        $result = $this->edge->reportOutbox(
            (int) $request->query('page', 1),
            $limit,
            (array) $request->query('filters', [])
        );
        $remote = $result['ok'] ? \Modules\Integrations\Services\ModirPayamakReportRows::messages($result['data'] ?? null) : [];
        $rows = $this->manager->withPatternText(
            $this->manager->mergeSent($this->manager->recentMessages($domain, $limit), $remote, max($limit, count($remote)))
        );

        return response()->json([
            'data' => ['entries' => $rows, 'messages' => $rows],
            'meta' => $result['meta'] ?? [],
        ]);
    }

    public function reportOutboxDetail(Request $request, string $id): JsonResponse
    {
        $this->domain($request);
        $result = $this->edge->reportOutboxById($id);

        return response()->json(['data' => $result['data']], $result['ok'] ? 200 : 422);
    }

    public function reportsMessages(Request $request): JsonResponse
    {
        $domain = $this->domain($request);
        $limit = max(1, (int) $request->query('limit', 20));
        $page = max(1, (int) $request->query('page', 1));
        $rows = $this->manager->sentHistory($domain, $page * $limit);
        $slice = array_slice($rows, ($page - 1) * $limit, $limit);

        return response()->json(['data' => ['messages' => array_values($slice), 'entries' => array_values($slice)]]);
    }

    public function patterns(Request $request): JsonResponse
    {
        $domain = $this->domain($request);
        try {
            app(\Modules\Integrations\Services\ModirPayamakPatternSync::class)->reconcileCached($domain);
        } catch (\Throwable) {
            // A rejected provider rename must not hide the pattern list.
        }
        $result = $this->edge->listPatterns($request->query());
        $rows = \Modules\Integrations\Services\ModirPayamakReportRows::patterns($result['data'] ?? null);

        return response()->json([
            'data' => ['data' => $rows, 'patterns' => $rows],
        ], $result['ok'] ? 200 : 422);
    }

    public function numbers(Request $request): JsonResponse
    {
        $domain = $this->domain($request);
        $account = $this->manager->formatAccountPublic($this->manager->getOrCreateAccount($domain));
        $numbers = is_array($account['numbers'] ?? null) ? $account['numbers'] : [];
        if ($numbers === []) {
            $result = $this->edge->listNumbers($request->query());
            $numbers = \Modules\Integrations\Services\ModirPayamakReportRows::lines($result['data'] ?? null);
        }

        return response()->json(['data' => ['numbers' => $numbers]]);
    }

    public function phonebooks(Request $request): JsonResponse
    {
        $this->domain($request);
        if ($request->isMethod('post')) {
            $payload = $request->validate(['name' => 'required|string|max:150']);
            $result = $this->edge->createPhonebook($payload);

            return response()->json(['data' => $result['data']], $result['ok'] ? 201 : 422);
        }
        $result = $this->edge->listPhonebooks($request->query());

        return response()->json(['data' => $result['data']], $result['ok'] ? 200 : 422);
    }

    public function phonebookContacts(Request $request, int $id): JsonResponse
    {
        $this->domain($request);
        if ($request->isMethod('post')) {
            $payload = $request->validate(['number' => 'required|string', 'name' => 'nullable|string|max:150']);
            $result = $this->edge->createPhonebookContact($id, $payload);

            return response()->json(['data' => $result['data']], $result['ok'] ? 201 : 422);
        }
        $result = $this->edge->listPhonebookContacts($id, $request->query());

        return response()->json(['data' => $result['data']], $result['ok'] ? 200 : 422);
    }

    public function drafts(Request $request): JsonResponse
    {
        $this->domain($request);
        if ($request->isMethod('post') || $request->isMethod('put') || $request->isMethod('patch')) {
            $payload = $request->validate([
                'id' => 'nullable',
                'title' => 'nullable|string|max:190',
                'message' => 'nullable|string',
                'body' => 'nullable|string',
            ]);
            if (empty($payload['message']) && ! empty($payload['body'])) {
                $payload['message'] = $payload['body'];
            }
            if (! empty($payload['id'])) {
                $result = $this->edge->updateDraft($payload['id'], $payload);
            } else {
                $result = $this->edge->createDraft($payload);
            }

            return response()->json(['data' => $result['data'] ?? $result], $result['ok'] ? 200 : 422);
        }

        $result = $this->edge->listDrafts($request->query());

        return response()->json(['data' => $result['data'] ?? []], $result['ok'] ? 200 : 422);
    }

    public function draftShow(Request $request, string $id): JsonResponse
    {
        $this->domain($request);
        if ($request->isMethod('delete')) {
            $result = $this->edge->deleteDraft($id);

            return response()->json(['data' => $result['data'] ?? ['ok' => $result['ok']]], $result['ok'] ? 200 : 422);
        }
        $result = $this->edge->listDrafts(array_merge($request->query(), ['id' => $id]));

        return response()->json(['data' => $result['data'] ?? []], $result['ok'] ? 200 : 422);
    }

    public function draftGroups(Request $request): JsonResponse
    {
        $this->domain($request);
        $result = $this->edge->listDraftGroups($request->query());

        return response()->json(['data' => $result['data'] ?? []], $result['ok'] ? 200 : 422);
    }

    public function phonebooksEdge(Request $request, string $sub = ''): JsonResponse
    {
        $this->domain($request);
        $body = $request->except(['domain', 'license_key', 'path']);
        $result = $this->edge->phonebooksEdge($request->method(), $sub, $body, $request->query());

        return response()->json(['data' => $result['data'] ?? []], $result['ok'] ? 200 : ($result['code'] ?: 422));
    }

    public function reportsBulk(Request $request, string $outboxId): JsonResponse
    {
        $this->domain($request);
        $result = $this->edge->reportBulk($outboxId, $request->query());

        return response()->json(['data' => $result['data'] ?? []], $result['ok'] ? 200 : 422);
    }

    public function cancelScheduled(Request $request): JsonResponse
    {
        $this->domain($request);
        $payload = $request->validate([
            'messages_outbox_id' => 'nullable|string|max:100',
            'outbox_id' => 'nullable|string|max:100',
            'id' => 'nullable|string|max:100',
        ]);
        $id = $payload['messages_outbox_id'] ?? $payload['outbox_id'] ?? $payload['id'] ?? '';
        if ($id === '') {
            return response()->json(['message' => 'messages_outbox_id is required', 'ok' => false], 422);
        }
        $result = $this->edge->cancelScheduled(['messages_outbox_id' => $id]);
        if (! ($result['ok'] ?? false)) {
            return response()->json([
                'ok' => false,
                'unavailable' => true,
                'message' => $result['message'] ?: 'Cancel scheduled is not available on Edge for this message.',
                'messages_outbox_id' => $id,
            ], 200);
        }

        return response()->json(['data' => array_merge(['ok' => true, 'messages_outbox_id' => $id], is_array($result['data']) ? $result['data'] : [])]);
    }

    public function newsletter(Request $request): JsonResponse
    {
        $this->domain($request);
        $result = $this->edge->newsletterUnavailable($request->method());

        return response()->json([
            'ok' => false,
            'unavailable' => true,
            'message' => $result['message'],
        ], 200);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function collectRecipients(array $data): array
    {
        $out = [];
        foreach ((array) ($data['recipients'] ?? []) as $value) {
            if (is_scalar($value) && trim((string) $value) !== '') {
                $out[] = trim((string) $value);
            }
        }
        if (isset($data['phone']) && is_scalar($data['phone']) && trim((string) $data['phone']) !== '') {
            $out[] = trim((string) $data['phone']);
        }

        return array_values(array_unique($out));
    }

    private function formatSendTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            $ts = (int) $value;
            if ($ts > 1_000_000_000_000) {
                $ts = (int) floor($ts / 1000);
            }
            if ($ts <= 0) {
                return null;
            }

            return \Carbon\Carbon::createFromTimestamp($ts, 'Asia/Tehran')->format('Y-m-d H:i:s');
        }
        $text = trim((string) $value);
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $text)) {
            return str_replace('T', ' ', substr($text, 0, 16)).':00';
        }

        return $text !== '' ? $text : null;
    }
}
