<?php

namespace Modules\Integrations\Http\Controllers;

use App\Http\Controllers\Api\PaginatesApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Integrations\Entities\IntegrationSetting;
use Modules\Integrations\Entities\ModirPayamakAccount;
use Modules\Integrations\Entities\ModirPayamakOrder;
use Modules\Integrations\Entities\ModirPayamakPackage;
use Modules\Integrations\Services\ModirPayamakEdgeClient;
use Modules\Integrations\Services\ModirPayamakManager;

class ModirPayamakAdminController extends Controller
{
    use PaginatesApi;

    public function __construct(
        private ModirPayamakEdgeClient $edge,
        private ModirPayamakManager $manager
    ) {}

    public function dashboard(): JsonResponse
    {
        return response()->json([
            'data' => [
                'configured' => $this->edge->isConfigured(),
                'accounts' => ModirPayamakAccount::query()->count(),
                'orders_pending' => ModirPayamakOrder::query()->where('status', 'pending')->count(),
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

    public function orders(Request $request): JsonResponse
    {
        return $this->paginatedResponse(ModirPayamakOrder::query()->with('package')->orderByDesc('created_at')->paginate($this->perPage($request)));
    }

    public function adminSend(Request $request): JsonResponse
    {
        $data = $request->validate([
            'domain' => 'required|string|max:255',
            'message' => 'required|string',
            'recipients' => 'required|array|min:1',
            'from_number' => 'nullable|string',
        ]);
        $domain = $this->manager->normalizeDomain($data['domain']);
        $from = $data['from_number'] ?? $this->edge->defaultFrom();
        $result = $this->edge->sendWebservice($from, $data['message'], $data['recipients']);
        if (! $result['ok']) {
            return response()->json(['message' => $result['message'] ?: 'Send failed'], 422);
        }
        $cost = count($data['recipients']) * $this->manager->pricePerUnit();
        $account = $this->manager->debit($domain, $cost, 'send');

        return response()->json(['data' => ['edge' => $result['data'], 'account' => $account]]);
    }

    public function messages(Request $request): JsonResponse
    {
        $result = $this->edge->reportInbox((int) $request->query('page', 1), (int) $request->query('limit', 20));

        return response()->json(['data' => $result['data'], 'meta' => $result['meta']], $result['ok'] ? 200 : 422);
    }
}
