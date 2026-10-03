<?php

namespace Modules\Integrations\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Integrations\Services\Payments\GatewayConfigStore;
use Modules\Integrations\Services\Payments\PaymentException;
use Modules\Integrations\Services\Payments\PaymentGatewayRegistry;

class PaymentGatewaySettingsController extends Controller
{
    public function __construct(
        private GatewayConfigStore $store,
        private PaymentGatewayRegistry $registry,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->store->publicList()]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'gateways' => 'required|array',
        ]);
        try {
            $saved = $this->store->saveAll($data['gateways']);
        } catch (PaymentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->details,
            ], $e->status);
        }

        return response()->json(['data' => $saved, 'message' => 'تنظیمات درگاه ذخیره شد.']);
    }

    public function test(string $code): JsonResponse
    {
        try {
            $config = $this->store->for($code);
            $result = $this->registry->get($code)->testConnection($config);
        } catch (PaymentException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        }

        return response()->json([
            'data' => $result,
            'message' => $result['message'],
        ], $result['ok'] ? 200 : 422);
    }
}
