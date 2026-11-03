<?php

namespace Modules\Marketplace\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Marketplace\Services\BasalamFinanceProxy;

class BasalamFinanceController extends Controller
{
    public function __construct(protected BasalamFinanceProxy $finance) {}

    public function proxy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'site_url' => 'required|url|max:500',
            'access_token' => 'nullable|string|max:8000',
            'op' => 'required|string|in:balance,active_settlements,history,banks,create_settlement',
            'page' => 'nullable|integer|min:1|max:1000',
            'per_page' => 'nullable|integer|min:1|max:100',
            'amount' => 'required_if:op,create_settlement|nullable|integer|min:1',
            'settlement_method' => 'required_if:op,create_settlement|nullable|integer|min:1',
            'bank_account_id' => 'nullable|integer|min:1',
            'investment_option_id' => 'nullable|integer|min:1',
        ]);

        $result = $this->finance->call($data);

        return response()->json($result['body'], $result['status']);
    }
}
