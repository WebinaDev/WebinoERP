<?php

namespace Modules\Sales\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Sales\Services\RahnService;
use Modules\Sales\Services\RahnSettingsService;

class RahnController extends Controller
{
    public function __construct(private readonly RahnService $rahn) {}

    public function getSettings(): JsonResponse
    {
        return response()->json([
            'data' => [
                'settings' => RahnSettingsService::get(),
            ],
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $payload = $request->all();
        if (isset($payload['settings']) && is_array($payload['settings'])) {
            $payload = $payload['settings'];
        }

        foreach (['catalog', 'review', 'sales_definition'] as $key) {
            if (isset($payload[$key]) && is_string($payload[$key])) {
                $decoded = json_decode($payload[$key], true);
                if (is_array($decoded)) {
                    $payload[$key] = $decoded;
                }
            }
        }

        $saved = RahnSettingsService::save($payload);

        return response()->json([
            'data' => [
                'settings' => $saved,
                'message' => 'تنظیمات رهن‌درصد ذخیره شد.',
            ],
            'message' => 'تنظیمات رهن‌درصد ذخیره شد.',
        ]);
    }

    public function calculate(Request $request): JsonResponse
    {
        $settings = RahnSettingsService::get();
        $result = $this->rahn->runCalc($request->all(), $settings, true);

        return response()->json(['data' => $result]);
    }

    public function quotesIndex(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->rahn->listQuotes($request->all())]);
    }

    public function quotesStore(Request $request): JsonResponse
    {
        $result = $this->rahn->saveQuote($request->all(), $request->user()?->id);

        return response()->json([
            'data' => array_merge($result, ['message' => 'پیش‌نویس ذخیره شد.']),
            'message' => 'پیش‌نویس ذخیره شد.',
        ], 201);
    }

    public function quotesLock(Request $request, int $id): JsonResponse
    {
        $result = $this->rahn->lockQuote($id, $request->all());

        return response()->json([
            'data' => array_merge($result, ['message' => 'ثابت و درصد قفل شدند.']),
            'message' => 'ثابت و درصد قفل شدند.',
        ]);
    }

    public function quotesContract(Request $request, int $id): JsonResponse
    {
        $result = $this->rahn->quoteToContract($id, $request->all(), $request->user()?->id);

        return response()->json([
            'data' => array_merge($result, ['message' => 'قرارداد رهن‌درصد ایجاد شد.']),
            'message' => 'قرارداد رهن‌درصد ایجاد شد.',
        ]);
    }

    public function quotesDestroy(int $id): JsonResponse
    {
        $this->rahn->deleteQuote($id);

        return response()->json([
            'data' => ['message' => 'پیش‌نویس حذف شد.'],
            'message' => 'پیش‌نویس حذف شد.',
        ]);
    }

    public function contractsIndex(): JsonResponse
    {
        return response()->json(['data' => $this->rahn->listContracts()]);
    }

    public function customersIndex(): JsonResponse
    {
        return response()->json(['data' => ['customers' => $this->rahn->listCustomers()]]);
    }

    public function statementsCalculate(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->rahn->statementCalculate($request->all())]);
    }

    public function statementsStore(Request $request): JsonResponse
    {
        $result = $this->rahn->statementSave($request->all(), $request->user()?->id);

        return response()->json([
            'data' => array_merge($result, ['message' => 'صورتحساب ماهانه ذخیره شد.']),
            'message' => 'صورتحساب ماهانه ذخیره شد.',
        ], 201);
    }

    public function statementsIndex(Request $request): JsonResponse
    {
        $contractId = (int) $request->query('contract_id', 0);

        return response()->json(['data' => $this->rahn->listStatements($contractId)]);
    }
}
