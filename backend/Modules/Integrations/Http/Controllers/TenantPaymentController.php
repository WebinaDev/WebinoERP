<?php

namespace Modules\Integrations\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Accounting\Http\Controllers\Concerns\VerifiesWebinocrmLicenseSignature;
use Modules\Core\Services\CoreLicenseResolver;
use Modules\Integrations\Entities\PaymentIntent;
use Modules\Integrations\Services\Payments\BillableResolver;
use Modules\Integrations\Services\Payments\GatewayConfigStore;
use Modules\Integrations\Services\Payments\PaymentException;
use Modules\Integrations\Services\Payments\PaymentOrchestrator;

/**
 * Tenant dashboard calls these routes. ERP verifies and settles.
 * The browser returns to return_url on the tenant dashboard.
 */
class TenantPaymentController extends Controller
{
    use VerifiesWebinocrmLicenseSignature;

    public function __construct(
        private PaymentOrchestrator $payments,
        private GatewayConfigStore $configs,
        private BillableResolver $bills,
    ) {}

    public function bills(Request $request): JsonResponse
    {
        if ($denied = $this->guard($request)) {
            return $denied;
        }

        return response()->json([
            'data' => $this->bills->outstanding((string) $request->input('domain')),
        ]);
    }

    public function gateways(Request $request): JsonResponse
    {
        if ($denied = $this->guard($request)) {
            return $denied;
        }
        $mode = $request->input('mode');

        return response()->json([
            'data' => [
                'cash' => $this->configs->optionsFor('cash'),
                'installment' => $this->configs->optionsFor('installment'),
                'gateways' => $this->configs->optionsFor(is_string($mode) ? $mode : null),
            ],
        ]);
    }

    public function quote(Request $request): JsonResponse
    {
        if ($denied = $this->guard($request)) {
            return $denied;
        }
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

    public function sessions(Request $request): JsonResponse
    {
        if ($denied = $this->guard($request)) {
            return $denied;
        }
        $data = $request->validate([
            'payable_type' => 'required|string|max:40',
            'payable_id' => 'nullable|string|max:64',
            'mode' => 'required|string|in:cash,installment',
            'gateway' => 'nullable|string|max:32',
            'return_url' => 'required|url|max:2000',
            'mobile' => 'nullable|string|max:20',
            'amount' => 'nullable|numeric|min:0',
            'description' => 'nullable|string|max:500',
            'idempotency_key' => 'nullable|string|max:80',
        ]);
        $data['domain'] = (string) $request->input('domain');
        try {
            $intent = $this->payments->start($data, null, (string) $request->input('domain'));
        } catch (PaymentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error' => $e->errorCode,
                'details' => $e->details,
            ], $e->status);
        }

        return response()->json([
            'data' => $intent->toApiArray(),
            'message' => 'نشست پرداخت ساخته شد.',
        ], 201);
    }

    public function show(Request $request, string $publicId): JsonResponse
    {
        if ($denied = $this->guard($request)) {
            return $denied;
        }
        $intent = PaymentIntent::query()->where('public_id', $publicId)->first();
        if (! $intent) {
            return response()->json(['message' => 'پرداخت پیدا نشد.'], 404);
        }
        $want = CoreLicenseResolver::normalizeDomain((string) $request->input('domain'));
        $have = $intent->domain ? CoreLicenseResolver::normalizeDomain((string) $intent->domain) : '';
        if ($have !== '' && $have !== $want) {
            return response()->json(['message' => 'این پرداخت به سایت شما تعلق ندارد.'], 403);
        }

        return response()->json(['data' => $intent->toApiArray()]);
    }

    private function guard(Request $request): ?JsonResponse
    {
        $request->validate([
            'domain' => 'required|string|max:255',
            'product' => 'nullable|string|max:255',
            'ts' => 'nullable|integer',
            'signature' => 'nullable|string|max:128',
        ]);
        if (! $this->verifyLicenseRequest($request)) {
            return response()->json(['message' => 'امضای درخواست معتبر نیست.'], 401);
        }

        return null;
    }
}
