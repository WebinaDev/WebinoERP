<?php

namespace Modules\Integrations\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Modules\Integrations\Entities\ModirPayamakOrder;
use Modules\Integrations\Entities\ModirPayamakPackage;
use Modules\Integrations\Services\ModirPayamakEdgeClient;
use Modules\Integrations\Services\ModirPayamakManager;

class ModirPayamakCustomerController extends Controller
{
    public function __construct(
        private ModirPayamakEdgeClient $edge,
        private ModirPayamakManager $manager
    ) {}

    private function domain(Request $request): string
    {
        $domain = (string) ($request->input('domain') ?? $request->query('domain', ''));
        $this->manager->assertLicensedDomain($domain);
        if (! $this->edge->isConfigured()) {
            abort(503, 'ModirPayamak is not configured');
        }

        return $this->manager->normalizeDomain($domain);
    }

    public function account(Request $request): JsonResponse
    {
        $domain = $this->domain($request);
        $account = $this->manager->getOrCreateAccount($domain);
        $credit = $this->edge->myCredit();

        return response()->json(['data' => ['account' => $account, 'edge_credit' => $credit['data']]]);
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
        ]);
        $domain = $this->domain($request);
        $package = ModirPayamakPackage::query()->findOrFail($data['package_id']);
        $authority = 'A'.strtoupper(bin2hex(random_bytes(16)));
        $order = ModirPayamakOrder::create([
            'domain' => $domain,
            'package_id' => $package->id,
            'amount' => $package->amount,
            'authority' => $authority,
            'status' => 'pending',
            'user_id' => $request->user()?->id,
        ]);
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
        $cached = Cache::get('modirpayamak:topup:'.$data['authority']);
        $order = ModirPayamakOrder::query()->where('authority', $data['authority'])->where('domain', $domain)->firstOrFail();
        if (($data['status'] ?? 'OK') !== 'OK') {
            $order->update(['status' => 'failed']);

            return response()->json(['message' => 'Payment failed'], 422);
        }
        $order->update(['status' => 'paid', 'ref_id' => $data['authority']]);
        $package = $order->package;
        $units = $package ? (float) $package->amount / $this->manager->pricePerUnit() : (float) $order->amount / $this->manager->pricePerUnit();
        $account = $this->manager->credit($domain, $units, 'topup', (string) $order->id, ['package_id' => $order->package_id]);
        Cache::forget('modirpayamak:topup:'.$data['authority']);

        return response()->json(['data' => ['order' => $order->fresh(), 'account' => $account], 'message' => 'Topup verified']);
    }

    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'domain' => 'required|string|max:255',
            'message' => 'required|string',
            'recipients' => 'required|array|min:1',
            'from_number' => 'nullable|string',
        ]);
        $domain = $this->domain($request);
        $from = $data['from_number'] ?? $this->edge->defaultFrom();
        $result = $this->edge->sendWebservice($from, $data['message'], $data['recipients']);
        if (! $result['ok']) {
            return response()->json(['message' => $result['message'] ?: 'Send failed', 'meta' => $result['meta']], 422);
        }
        $cost = count($data['recipients']) * $this->manager->pricePerUnit();
        $account = $this->manager->debit($domain, $cost, 'send', null, ['recipients' => count($data['recipients'])]);

        return response()->json(['data' => ['edge' => $result['data'], 'account' => $account]]);
    }

    public function sendPeerToPeer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'domain' => 'required|string|max:255',
            'groups' => 'required|array|min:1',
            'from_number' => 'nullable|string',
        ]);
        $domain = $this->domain($request);
        $from = $data['from_number'] ?? $this->edge->defaultFrom();
        $result = $this->edge->sendPeerToPeer($data['groups'], $from);
        if (! $result['ok']) {
            return response()->json(['message' => $result['message'] ?: 'Send failed'], 422);
        }

        return response()->json(['data' => $result['data']]);
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
        $this->domain($request);
        $result = $this->edge->reportOutbox(
            (int) $request->query('page', 1),
            (int) $request->query('limit', 20),
            (array) $request->query('filters', [])
        );

        return response()->json(['data' => $result['data'], 'meta' => $result['meta']], $result['ok'] ? 200 : 422);
    }

    public function reportOutboxDetail(Request $request, string $id): JsonResponse
    {
        $this->domain($request);
        $result = $this->edge->reportOutboxById($id);

        return response()->json(['data' => $result['data']], $result['ok'] ? 200 : 422);
    }

    public function reportsMessages(Request $request): JsonResponse
    {
        $this->domain($request);
        $result = $this->edge->reportInbox(
            (int) $request->query('page', 1),
            (int) $request->query('limit', 20),
            (array) $request->query('filters', [])
        );

        return response()->json(['data' => $result['data'], 'meta' => $result['meta']], $result['ok'] ? 200 : 422);
    }

    public function patterns(Request $request): JsonResponse
    {
        $this->domain($request);
        $result = $this->edge->listPatterns($request->query());

        return response()->json(['data' => $result['data']], $result['ok'] ? 200 : 422);
    }

    public function numbers(Request $request): JsonResponse
    {
        $this->domain($request);
        $result = $this->edge->listNumbers($request->query());

        return response()->json(['data' => $result['data']], $result['ok'] ? 200 : 422);
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
}
