<?php

namespace Modules\Integrations\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Integrations\Entities\PaymentIntent;
use Modules\Integrations\Services\Payments\BillableResolver;
use Modules\Integrations\Services\Payments\GatewayConfigStore;
use Modules\Integrations\Services\Payments\PaymentException;
use Modules\Integrations\Services\Payments\PaymentOrchestrator;

class PaymentCheckoutController extends Controller
{
    public function __construct(
        private PaymentOrchestrator $payments,
        private GatewayConfigStore $configs,
        private BillableResolver $bills,
    ) {}

    public function options(Request $request): JsonResponse
    {
        $mode = $request->query('mode');
        $mode = is_string($mode) && $mode !== '' ? $mode : null;

        return response()->json([
            'data' => [
                'gateways' => $this->configs->optionsFor($mode),
                'cash' => $this->configs->optionsFor('cash'),
                'installment' => $this->configs->optionsFor('installment'),
            ],
        ]);
    }

    public function quote(Request $request): JsonResponse
    {
        $data = $request->validate([
            'gateway' => 'required|string|max:32',
            'mode' => 'required|string|in:cash,installment',
            'base_amount' => 'required|integer|min:0',
        ]);
        try {
            $quote = $this->payments->quote($data['gateway'], $data['mode'], (int) $data['base_amount']);
        } catch (PaymentException $e) {
            return response()->json(['message' => $e->getMessage(), 'error' => $e->errorCode], $e->status);
        }

        return response()->json(['data' => $quote]);
    }

    public function bills(Request $request): JsonResponse
    {
        $domain = $request->query('domain');

        return response()->json([
            'data' => $this->bills->outstanding(is_string($domain) ? $domain : null),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'payable_type' => 'required|string|max:40',
            'payable_id' => 'nullable|string|max:64',
            'mode' => 'required|string|in:cash,installment',
            'gateway' => 'nullable|string|max:32',
            'return_url' => 'nullable|url|max:2000',
            'domain' => 'nullable|string|max:255',
            'mobile' => 'nullable|string|max:20',
            'amount' => 'nullable|numeric|min:0',
            'description' => 'nullable|string|max:500',
            'idempotency_key' => 'nullable|string|max:80',
        ]);
        try {
            $intent = $this->payments->start($data, $request->user()?->id);
        } catch (PaymentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error' => $e->errorCode,
                'details' => $e->details,
            ], $e->status);
        }

        return response()->json(['data' => $intent->toApiArray(), 'message' => 'پرداخت ساخته شد.'], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $query = PaymentIntent::query()->latest();
        if ($request->filled('payable_type')) {
            $query->where('payable_type', $request->string('payable_type'));
        }
        if ($request->filled('payable_id')) {
            $query->where('payable_id', $request->string('payable_id'));
        }
        if ($request->filled('domain')) {
            $query->where('domain', $request->string('domain'));
        }
        $rows = $query->limit(50)->get()->map->toApiArray()->values();

        return response()->json(['data' => $rows]);
    }

    public function show(string $publicId): JsonResponse
    {
        $intent = PaymentIntent::query()->where('public_id', $publicId)->first();
        if (! $intent) {
            return response()->json(['message' => 'پرداخت پیدا نشد.'], 404);
        }

        return response()->json([
            'data' => $intent->toApiArray(),
            'ledger' => $intent->ledger()->latest()->get(['entry_type', 'amount', 'status', 'note', 'created_at']),
        ]);
    }

    public function cancel(Request $request, string $publicId): JsonResponse
    {
        $intent = PaymentIntent::query()->where('public_id', $publicId)->first();
        if (! $intent) {
            return response()->json(['message' => 'پرداخت پیدا نشد.'], 404);
        }
        try {
            $intent = $this->payments->cancel($intent, $request->boolean('confirm'));
        } catch (PaymentException $e) {
            return response()->json(['message' => $e->getMessage(), 'error' => $e->errorCode], $e->status);
        }

        return response()->json(['data' => $intent->toApiArray(), 'message' => 'وضعیت پرداخت به‌روز شد.']);
    }
}
