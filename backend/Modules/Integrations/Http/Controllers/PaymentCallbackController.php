<?php

namespace Modules\Integrations\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Integrations\Services\Payments\PaymentOrchestrator;

class PaymentCallbackController extends Controller
{
    public function __construct(private PaymentOrchestrator $payments) {}

    public function handle(Request $request, string $gateway): JsonResponse|RedirectResponse
    {
        return $this->payments->handleCallback($request, $gateway);
    }
}
