<?php

namespace Modules\Integrations\Http\Controllers;

use App\Http\Controllers\Api\PaginatesApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Integrations\Entities\IntegrationSetting;
use Modules\Integrations\Entities\ModirPayamakAccount;
use Modules\Integrations\Entities\ModirPayamakBalanceLedger;
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
        return $this->paginatedResponse(ModirPayamakAccount::query()->orderBy('domain')->paginate($this->perPage($request)));
    }

    public function customerBalance(Request $request): JsonResponse
    {
        $data = $request->validate([
            'domain' => 'required|string|max:255',
            'amount' => 'required|numeric',
            'type' => 'nullable|in:adjust,refund',
        ]);
        $domain = $this->manager->normalizeDomain($data['domain']);
        $account = $this->manager->credit($domain, (float) $data['amount'], $data['type'] ?? 'adjust');

        return response()->json(['data' => $account, 'message' => 'Balance updated']);
    }

    public function packagesIndex(Request $request): JsonResponse
    {
        return $this->paginatedResponse(ModirPayamakPackage::query()->orderBy('sort_order')->paginate($this->perPage($request)));
    }

    public function packagesStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'amount' => 'required|numeric|min:0',
            'sms_units' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);
        $package = ModirPayamakPackage::create($data);

        return response()->json(['data' => $package, 'message' => 'Package saved'], 201);
    }

    public function packagesDestroy(ModirPayamakPackage $package): JsonResponse
    {
        $package->delete();

        return response()->noContent();
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
        return $this->paginatedResponse(ModirPayamakOrder::query()->with('package')->orderByDesc('created_at')->paginate($this->perPage($request)));
    }

    public function adminSend(Request $request): JsonResponse
    {
        $data = $request->validate([
            'domain' => 'nullable|string|max:255',
            'message' => 'required|string',
            'recipients' => 'required|array|min:1',
            'from_number' => 'nullable|string',
            'debit_tenant' => 'nullable|boolean',
        ]);
        if (! $this->edge->isConfigured()) {
            abort(503, 'ModirPayamak is not configured');
        }
        $from = $data['from_number'] ?? $this->edge->defaultFrom();
        $result = $this->edge->sendWebservice($from, $data['message'], $data['recipients']);
        if (! $result['ok']) {
            return response()->json(['message' => $result['message'] ?: 'Send failed'], 422);
        }

        $account = null;
        $debit = (bool) ($data['debit_tenant'] ?? false);
        $domain = $this->manager->normalizeDomain((string) ($data['domain'] ?? ''));
        if ($debit) {
            if ($domain === '') {
                return response()->json(['message' => 'Domain is required'], 422);
            }
            $cost = count($data['recipients']) * $this->manager->pricePerUnit();
            $account = $this->manager->debit($domain, $cost, 'send');
        }

        return response()->json(['data' => ['edge' => $result['data'], 'account' => $account]]);
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
            $payload = $request->validate(['name' => 'required|string|max:150']);
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
            ]);
            $result = $this->edge->createPhonebookContact($id, $payload);

            return response()->json(['data' => $result['data']], $result['ok'] ? 201 : 422);
        }
        $result = $this->edge->listPhonebookContacts($id, $request->query());

        return response()->json(['data' => $result['data']], $result['ok'] ? 200 : 422);
    }

    public function messages(Request $request): JsonResponse
    {
        $result = $this->edge->reportInbox((int) $request->query('page', 1), (int) $request->query('limit', 20));

        return response()->json(['data' => $result['data'], 'meta' => $result['meta']], $result['ok'] ? 200 : 422);
    }
}
