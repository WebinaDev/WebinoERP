<?php

namespace Modules\Integrations\Http\Controllers;

use App\Http\Controllers\Api\PaginatesApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Integrations\Entities\IntegrationSetting;
use Modules\Integrations\Entities\ModirPayamakAccount;
use Modules\Integrations\Entities\ModirPayamakBalanceLedger;
use Modules\Integrations\Entities\ModirPayamakMessage;
use Modules\Integrations\Entities\ModirPayamakOrder;
use Modules\Integrations\Entities\ModirPayamakPackage;
use Modules\Integrations\Entities\ModirPayamakSecretary;
use Modules\Integrations\Entities\ModirPayamakTariff;
use Modules\Integrations\Services\ModirPayamakEdgeClient;
use Modules\Integrations\Services\ModirPayamakManager;

class ModirPayamakAdminController extends Controller
{
    use PaginatesApi;

    private const SECRETARY_TYPES = ['auto_reply', 'inbox_forward', 'code_reader', 'membership'];

    public function __construct(
        private ModirPayamakEdgeClient $edge,
        private ModirPayamakManager $manager
    ) {}

    public function dashboard(): JsonResponse
    {
        $configured = $this->edge->isConfigured();
        $credit = $configured ? $this->edge->myCredit() : ['data' => null];
        $stats = [
            'total_customers' => ModirPayamakAccount::query()->count(),
            'sent_today' => ModirPayamakBalanceLedger::query()
                ->where('type', 'send')
                ->whereDate('created_at', now()->toDateString())
                ->count(),
            'pending_orders' => ModirPayamakOrder::query()->where('status', 'pending')->count(),
            'reseller_credit' => is_array($credit['data'] ?? null) ? $credit['data'] : ($credit['data'] ?? null),
            'price_per_unit' => $this->manager->pricePerUnit(),
            'configured' => $configured,
        ];

        return response()->json([
            'data' => [
                'stats' => $stats,
                'configured' => $configured,
                'accounts' => $stats['total_customers'],
                'orders_pending' => $stats['pending_orders'],
                'orders_paid' => ModirPayamakOrder::query()->where('status', 'paid')->count(),
            ],
        ]);
    }

    public function proxy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'method' => 'required|string|in:GET,POST,PUT,PATCH,DELETE',
            'path' => 'required|string|max:500',
            'body' => 'nullable|array',
            'query' => 'nullable|array',
        ]);
        if (! $this->edge->isConfigured()) {
            abort(503, 'ModirPayamak is not configured');
        }
        $result = $this->edge->request($data['method'], $data['path'], $data['body'] ?? [], $data['query'] ?? []);

        return response()->json(['data' => $result['data'], 'meta' => $result['meta']], $result['ok'] ? 200 : 422);
    }

    public function customers(Request $request): JsonResponse
    {
        $accounts = ModirPayamakAccount::query()->orderBy('domain')->get()
            ->map(fn (ModirPayamakAccount $a) => $this->manager->formatAccountPublic($a))
            ->values()
            ->all();

        if ($request->boolean('paginate')) {
            $page = max(1, (int) $request->query('page', 1));
            $perPage = $this->perPage($request);
            $slice = array_slice($accounts, ($page - 1) * $perPage, $perPage);

            return response()->json([
                'data' => $slice,
                'meta' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => count($accounts),
                    'last_page' => max(1, (int) ceil(count($accounts) / $perPage)),
                ],
            ]);
        }

        return response()->json(['data' => ['accounts' => $accounts]]);
    }

    public function customerBalance(Request $request): JsonResponse
    {
        $data = $request->validate([
            'domain' => 'required|string|max:255',
            'amount' => 'required|numeric',
            'type' => 'nullable|in:adjust,refund',
            'note' => 'nullable|string|max:500',
        ]);
        $domain = $this->manager->normalizeDomain($data['domain']);
        $meta = [];
        if (! empty($data['note'])) {
            $meta['note'] = $data['note'];
        }
        $account = $this->manager->credit($domain, (float) $data['amount'], $data['type'] ?? 'adjust', null, $meta);

        return response()->json([
            'data' => [
                'account' => $this->manager->formatAccountPublic($account),
            ],
            'message' => 'Balance updated',
        ]);
    }

    public function customerLedger(Request $request): JsonResponse
    {
        $data = $request->validate([
            'domain' => 'required|string|max:255',
            'page' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);
        $domain = $this->manager->normalizeDomain($data['domain']);
        $account = $this->manager->getOrCreateAccount($domain);

        return response()->json([
            'data' => [
                'ledger' => $this->manager->getLedger($domain, (int) ($data['page'] ?? 1), (int) ($data['limit'] ?? 50)),
                'account' => $this->manager->formatAccountPublic($account),
            ],
        ]);
    }

    public function packagesIndex(Request $request): JsonResponse
    {
        $packages = ModirPayamakPackage::query()->orderBy('sort_order')->get()
            ->map(fn (ModirPayamakPackage $p) => $this->manager->formatPackage($p))
            ->values()
            ->all();

        if ($request->boolean('paginate')) {
            return $this->paginatedResponse(ModirPayamakPackage::query()->orderBy('sort_order')->paginate($this->perPage($request)));
        }

        return response()->json(['data' => ['packages' => $packages]]);
    }

    public function packagesStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => 'nullable|integer|min:1',
            'name' => 'required|string|max:150',
            'amount' => 'required|numeric|min:0',
            'sms_units' => 'nullable|integer|min:0',
            'bonus' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
            'status' => 'nullable|string|in:active,inactive',
            'sort_order' => 'nullable|integer|min:0',
            'sort' => 'nullable|integer|min:0',
        ]);

        $smsUnits = (int) ($data['sms_units'] ?? $data['bonus'] ?? 0);
        $sortOrder = (int) ($data['sort_order'] ?? $data['sort'] ?? 0);
        $isActive = array_key_exists('is_active', $data)
            ? (bool) $data['is_active']
            : (($data['status'] ?? 'active') === 'active');

        $payload = [
            'name' => $data['name'],
            'amount' => $data['amount'],
            'sms_units' => $smsUnits,
            'sort_order' => $sortOrder,
            'is_active' => $isActive,
        ];

        if (! empty($data['id'])) {
            $package = ModirPayamakPackage::query()->findOrFail((int) $data['id']);
            $package->update($payload);
        } else {
            $package = ModirPayamakPackage::query()->create($payload);
        }

        return response()->json([
            'data' => ['id' => $package->id, 'package' => $this->manager->formatPackage($package->fresh())],
            'message' => 'Package saved',
        ], empty($data['id']) ? 201 : 200);
    }

    public function packagesDestroy(ModirPayamakPackage $package): JsonResponse
    {
        $package->delete();

        return response()->json(['data' => ['message' => 'Deleted']]);
    }

    public function tariffsIndex(): JsonResponse
    {
        return response()->json([
            'data' => [
                'tariffs' => ModirPayamakTariff::query()->orderBy('sort')->orderBy('line_type')->orderBy('operator')->get(),
                'tax_percent' => (float) IntegrationSetting::getString('modirpayamak', 'sms_tax_percent', '10'),
                'surcharge_rial' => (float) IntegrationSetting::getString('modirpayamak', 'sms_surcharge_rial', '40'),
            ],
        ]);
    }

    public function tariffsStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => 'nullable|integer|min:1',
            'line_type' => 'required|string|max:50',
            'operator' => 'required|string|in:mci,other',
            'rate_fa' => 'required|numeric|min:0',
            'rate_la' => 'required|numeric|min:0',
            'sort' => 'nullable|integer',
            'status' => 'nullable|string|in:active,inactive',
        ]);

        $payload = [
            'line_type' => trim($data['line_type']),
            'operator' => $data['operator'],
            'rate_fa' => (float) $data['rate_fa'],
            'rate_la' => (float) $data['rate_la'],
            'sort' => (int) ($data['sort'] ?? 0),
            'status' => $data['status'] ?? 'active',
        ];

        if (! empty($data['id'])) {
            $tariff = ModirPayamakTariff::query()->findOrFail((int) $data['id']);
            $tariff->update($payload);
        } else {
            $tariff = ModirPayamakTariff::query()->create($payload);
        }

        return response()->json(['data' => ['id' => $tariff->id], 'message' => 'Tariff saved']);
    }

    public function tariffsDestroy(int $id): JsonResponse
    {
        $tariff = ModirPayamakTariff::query()->findOrFail($id);
        $tariff->delete();

        return response()->json(['data' => ['message' => 'Deleted']]);
    }

    public function secretariesIndex(Request $request): JsonResponse
    {
        $domain = $this->manager->normalizeDomain((string) $request->query('domain', ''));
        if ($domain === '') {
            return response()->json(['message' => 'Domain is required'], 422);
        }

        $rows = ModirPayamakSecretary::query()
            ->where('domain', $domain)
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => ['secretaries' => $rows]]);
    }

    public function secretariesStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'domain' => 'required|string|max:255',
            'id' => 'nullable|integer|min:1',
            'type' => 'nullable|string|in:'.implode(',', self::SECRETARY_TYPES),
            'name' => 'nullable|string|max:191',
            'keywords' => 'nullable|string|max:2000',
            'reply_body' => 'nullable|string',
            'pattern_code' => 'nullable|string|max:100',
            'forward_to' => 'nullable|string|max:40',
            'enabled' => 'nullable|boolean',
        ]);

        $domain = $this->manager->normalizeDomain($data['domain']);
        $type = $data['type'] ?? 'auto_reply';
        $keywords = trim((string) ($data['keywords'] ?? '*'));
        if ($keywords === '') {
            $keywords = '*';
        }

        $payload = [
            'domain' => $domain,
            'type' => $type,
            'name' => trim((string) ($data['name'] ?? $type)) ?: $type,
            'keywords' => $keywords,
            'reply_body' => (string) ($data['reply_body'] ?? ''),
            'pattern_code' => (string) ($data['pattern_code'] ?? ''),
            'forward_to' => (string) ($data['forward_to'] ?? ''),
            'enabled' => array_key_exists('enabled', $data) ? (bool) $data['enabled'] : true,
        ];

        if (! empty($data['id'])) {
            $rule = ModirPayamakSecretary::query()
                ->where('domain', $domain)
                ->where('id', (int) $data['id'])
                ->firstOrFail();
            $rule->update($payload);
        } else {
            $rule = ModirPayamakSecretary::query()->create($payload);
        }

        return response()->json(['data' => ['ok' => true, 'rule' => $rule], 'message' => 'Saved']);
    }

    public function secretariesDestroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'domain' => 'required|string|max:255',
            'id' => 'required|integer|min:1',
        ]);
        $domain = $this->manager->normalizeDomain($data['domain']);
        $deleted = ModirPayamakSecretary::query()
            ->where('domain', $domain)
            ->where('id', (int) $data['id'])
            ->delete();

        if (! $deleted) {
            return response()->json(['message' => 'Secretary rule not found'], 404);
        }

        return response()->json(['data' => ['ok' => true, 'id' => (int) $data['id']]]);
    }

    public function orders(Request $request): JsonResponse
    {
        $paginator = ModirPayamakOrder::query()->with('package')->orderByDesc('created_at')->paginate($this->perPage($request));
        $orders = collect($paginator->items())->map(function (ModirPayamakOrder $order) {
            return [
                'id' => $order->id,
                'domain' => $order->domain,
                'package_id' => $order->package_id,
                'amount' => (float) $order->amount,
                'credit_amount' => (float) ($order->credit_amount ?? $order->package?->sms_units ?? 0),
                'status' => $order->status,
                'authority' => $order->authority,
                'ref_id' => $order->ref_id,
                'created_at' => optional($order->created_at)?->toIso8601String(),
            ];
        })->values()->all();

        return response()->json([
            'data' => [
                'orders' => $orders,
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function adminSend(Request $request): JsonResponse
    {
        $rawPayload = $request->input('payload');
        if (is_string($rawPayload) && $rawPayload !== '') {
            $decoded = json_decode($rawPayload, true);
            if (is_array($decoded)) {
                $request->merge($decoded);
            }
        }

        $data = $request->validate([
            'domain' => 'nullable|string|max:255',
            'message' => 'nullable|string',
            'code' => 'nullable|string|max:100',
            'sending_type' => 'nullable|string|in:webservice,pattern',
            'recipients' => 'required|array|min:1',
            'recipients.*' => 'string|max:40',
            'from_number' => 'nullable|string',
            'params' => 'nullable|array',
            'debit_tenant' => 'nullable|boolean',
        ]);

        if (! $this->edge->isConfigured()) {
            abort(503, 'ModirPayamak is not configured');
        }

        $sendingType = $data['sending_type'] ?? (! empty($data['code']) ? 'pattern' : 'webservice');
        $from = $data['from_number'] ?? $this->edge->defaultFrom();
        $recipients = array_values($data['recipients']);

        if ($sendingType === 'pattern') {
            $code = (string) ($data['code'] ?? '');
            if ($code === '') {
                return response()->json(['message' => 'Pattern code is required'], 422);
            }
            $params = is_array($data['params'] ?? null) ? $data['params'] : [];
            $result = $this->edge->sendPattern($from, $code, $recipients, $params);
            $messageBody = $code;
        } else {
            $message = (string) ($data['message'] ?? '');
            if ($message === '') {
                return response()->json(['message' => 'Message is required'], 422);
            }
            $result = $this->edge->sendWebservice($from, $message, $recipients);
            $messageBody = $message;
        }

        if (! $result['ok']) {
            return response()->json(['message' => $result['message'] ?: 'Send failed'], 422);
        }

        $account = null;
        $debit = (bool) ($data['debit_tenant'] ?? false);
        $domain = $this->manager->normalizeDomain((string) ($data['domain'] ?? ''));
        $cost = count($recipients) * $this->manager->pricePerUnit();
        if ($debit) {
            if ($domain === '') {
                return response()->json(['message' => 'Domain is required'], 422);
            }
            $account = $this->manager->debit($domain, $cost, 'send');
        }

        ModirPayamakMessage::query()->create([
            'domain' => $domain !== '' ? $domain : null,
            'sending_type' => $sendingType,
            'from_number' => $from,
            'pattern_code' => $sendingType === 'pattern' ? ($data['code'] ?? null) : null,
            'message' => $messageBody,
            'recipients' => $recipients,
            'status' => 'sent',
            'cost' => $debit ? $cost : 0,
            'edge_payload' => is_array($result['data'] ?? null) ? $result['data'] : ['raw' => $result['data']],
        ]);

        return response()->json([
            'data' => [
                'ok' => true,
                'edge' => $result['data'],
                'account' => $account ? $this->manager->formatAccountPublic($account) : null,
            ],
        ]);
    }

    public function attachNumber(Request $request): JsonResponse
    {
        $data = $request->validate([
            'domain' => 'required|string|max:255',
            'number' => 'required|string|max:50',
            'role' => 'nullable|string|in:service,personal,marketing',
            'label' => 'nullable|string|max:255',
        ]);
        $number = $this->manager->attachDomainNumber(
            $data['domain'],
            $data['number'],
            $data['role'] ?? 'service',
            (string) ($data['label'] ?? '')
        );
        $domain = $this->manager->normalizeDomain($data['domain']);

        return response()->json([
            'data' => [
                'message' => 'Number attached',
                'number' => $number,
                'attachments' => $this->manager->getNumberAttachments($data['number']),
                'account' => $this->manager->formatAccountPublic($this->manager->getOrCreateAccount($domain)),
            ],
            'message' => 'Number attached',
        ]);
    }

    public function detachNumber(Request $request): JsonResponse
    {
        $data = $request->validate([
            'domain' => 'required|string|max:255',
            'role' => 'nullable|string|in:service,personal,marketing',
            'number' => 'nullable|string|max:50',
        ]);
        $domain = $this->manager->normalizeDomain($data['domain']);
        $this->manager->detachDomainNumber($domain, $data['role'] ?? 'service', $data['number'] ?? null);

        return response()->json([
            'data' => [
                'message' => 'Number detached',
                'account' => $this->manager->formatAccountPublic($this->manager->getOrCreateAccount($domain)),
            ],
            'message' => 'Number detached',
        ]);
    }

    public function attachPattern(Request $request): JsonResponse
    {
        $data = $request->validate([
            'domain' => 'required|string|max:255',
            'scope' => 'required|string|in:order_customer,order_admin,site',
            'event_key' => 'required|string|max:80',
            'pattern_code' => 'required|string|max:100',
            'param_map' => 'nullable',
        ]);
        $paramMap = $data['param_map'] ?? null;
        if (is_string($paramMap) && $paramMap !== '') {
            $decoded = json_decode($paramMap, true);
            $paramMap = is_array($decoded) ? $decoded : null;
        }
        if (! is_array($paramMap)) {
            $paramMap = null;
        }

        $result = $this->manager->attachPattern(
            $data['domain'],
            $data['scope'],
            $data['event_key'],
            $data['pattern_code'],
            $paramMap
        );

        return response()->json([
            'data' => [
                'message' => 'Pattern attached to shop',
                'result' => $result,
                'registry' => $this->manager->listPatternRegistry($data['domain']),
            ],
            'message' => 'Pattern attached to shop',
        ]);
    }

    public function detachPattern(Request $request): JsonResponse
    {
        $data = $request->validate([
            'domain' => 'required|string|max:255',
            'scope' => 'required|string|in:order_customer,order_admin,site',
            'event_key' => 'required|string|max:80',
        ]);
        $registry = $this->manager->detachPattern($data['domain'], $data['scope'], $data['event_key']);

        return response()->json([
            'data' => [
                'message' => 'Pattern detached',
                'registry' => $registry,
            ],
            'message' => 'Pattern detached',
        ]);
    }

    public function patternRegistry(Request $request): JsonResponse
    {
        $domain = $this->manager->normalizeDomain((string) $request->query('domain', ''));

        return response()->json([
            'data' => [
                'registry' => $this->manager->listPatternRegistry($domain),
                'events' => array_merge(ModirPayamakManager::ORDER_EVENTS, ModirPayamakManager::SITE_EVENTS),
            ],
        ]);
    }

    public function reportsOutbox(Request $request): JsonResponse
    {
        if (! $this->edge->isConfigured()) {
            abort(503, 'ModirPayamak is not configured');
        }
        $result = $this->edge->reportOutbox(
            (int) $request->query('page', 1),
            (int) $request->query('limit', 20),
            (array) $request->query('filters', [])
        );

        return response()->json(['data' => $result['data'], 'meta' => $result['meta']], $result['ok'] ? 200 : 422);
    }

    public function reportsInbox(Request $request): JsonResponse
    {
        if (! $this->edge->isConfigured()) {
            abort(503, 'ModirPayamak is not configured');
        }
        $result = $this->edge->reportInbox(
            (int) $request->query('page', 1),
            (int) $request->query('limit', 20),
            (array) $request->query('filters', [])
        );

        return response()->json(['data' => $result['data'], 'meta' => $result['meta']], $result['ok'] ? 200 : 422);
    }

    public function reportOutboxDetail(string $id): JsonResponse
    {
        if (! $this->edge->isConfigured()) {
            abort(503, 'ModirPayamak is not configured');
        }
        $result = $this->edge->reportOutboxById($id);

        return response()->json(['data' => $result['data']], $result['ok'] ? 200 : 422);
    }

    public function patterns(Request $request): JsonResponse
    {
        if (! $this->edge->isConfigured()) {
            abort(503, 'ModirPayamak is not configured');
        }
        $result = $this->edge->listPatterns($request->query());

        return response()->json(['data' => $result['data']], $result['ok'] ? 200 : 422);
    }

    public function numbers(Request $request): JsonResponse
    {
        if (! $this->edge->isConfigured()) {
            abort(503, 'ModirPayamak is not configured');
        }
        $result = $this->edge->listNumbers($request->query());

        return response()->json(['data' => $result['data']], $result['ok'] ? 200 : 422);
    }

    public function phonebooks(Request $request): JsonResponse
    {
        if (! $this->edge->isConfigured()) {
            abort(503, 'ModirPayamak is not configured');
        }
        if ($request->isMethod('post')) {
            $payload = $request->validate(['name' => 'required|string|max:150', 'title' => 'nullable|string|max:150']);
            if (empty($payload['title'])) {
                $payload['title'] = $payload['name'];
            }
            $result = $this->edge->createPhonebook($payload);

            return response()->json(['data' => $result['data']], $result['ok'] ? 201 : 422);
        }
        $result = $this->edge->listPhonebooks($request->query());

        return response()->json(['data' => $result['data']], $result['ok'] ? 200 : 422);
    }

    public function phonebookContacts(Request $request, int $id): JsonResponse
    {
        if (! $this->edge->isConfigured()) {
            abort(503, 'ModirPayamak is not configured');
        }
        if ($request->isMethod('post')) {
            $payload = $request->validate([
                'number' => 'required|string|max:40',
                'name' => 'nullable|string|max:150',
                'phone' => 'nullable|string|max:40',
            ]);
            if (empty($payload['phone'])) {
                $payload['phone'] = $payload['number'];
            }
            $result = $this->edge->createPhonebookContact($id, $payload);

            return response()->json(['data' => $result['data']], $result['ok'] ? 201 : 422);
        }
        $result = $this->edge->listPhonebookContacts($id, $request->query());

        return response()->json(['data' => $result['data']], $result['ok'] ? 200 : 422);
    }

    public function messages(Request $request): JsonResponse
    {
        $domain = $this->manager->normalizeDomain((string) $request->query('domain', ''));
        $page = max(1, (int) $request->query('page', 1));
        $limit = min(100, max(1, (int) $request->query('limit', 20)));

        $query = ModirPayamakMessage::query()->orderByDesc('id');
        if ($domain !== '') {
            $query->where('domain', $domain);
        }
        $rows = $query->forPage($page, $limit)->get()->map(fn (ModirPayamakMessage $m) => [
            'id' => $m->id,
            'domain' => $m->domain,
            'status' => $m->status,
            'sending_type' => $m->sending_type,
            'cost' => (float) $m->cost,
            'message' => $m->message,
            'from_number' => $m->from_number,
            'pattern_code' => $m->pattern_code,
            'created_at' => optional($m->created_at)?->toIso8601String(),
        ])->values()->all();

        return response()->json(['data' => ['messages' => $rows]]);
    }
}
