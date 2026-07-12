<?php

namespace Modules\Accounting\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Accounting\Entities\AccUserDefault;
use Modules\Core\Entities\SystemSetting;

class AccountingSettingsController extends Controller
{
    public function settings(): JsonResponse
    {
        $currency = SystemSetting::get('accounting_currency', 'IRR');
        $fyId = SystemSetting::get('accounting_default_fiscal_year_id');

        return response()->json([
            'data' => [
                'currency' => $currency,
                'fiscal_year_id' => $fyId ? (int) $fyId : null,
            ],
        ]);
    }

    public function userDefaults(Request $request): JsonResponse
    {
        $uid = $request->user()->id;
        $row = AccUserDefault::query()->firstOrCreate(['user_id' => $uid]);

        return response()->json(['data' => $row]);
    }

    public function userDefaultsPut(Request $request): JsonResponse
    {
        $uid = $request->user()->id;
        $data = $request->validate([
            'fiscal_year_id' => 'nullable|exists:acc_fiscal_years,id',
            'cash_account_id' => 'nullable|exists:acc_cash_accounts,id',
            'warehouse_id' => 'nullable|exists:acc_warehouses,id',
            'price_list_id' => 'nullable|exists:acc_price_lists,id',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
        ]);
        $row = AccUserDefault::query()->updateOrCreate(['user_id' => $uid], $data);

        return response()->json(['data' => $row]);
    }
}
