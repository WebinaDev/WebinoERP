<?php

namespace Modules\Sales\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Sales\Entities\SalesQuote;
use Modules\Sales\Services\OrderToCashService;

class QuoteController extends Controller
{
    public function index(): JsonResponse
    {
        $quotes = SalesQuote::query()->with('lines')->latest()->paginate(25);

        return response()->json($quotes);
    }

    public function store(Request $request, OrderToCashService $service): JsonResponse
    {
        $data = $request->validate([
            'customer_name' => 'required|string|max:191',
            'customer_id' => 'nullable|integer',
            'crm_deal_id' => 'nullable|integer',
            'issue_date' => 'nullable|date',
            'valid_until' => 'nullable|date',
            'terms_and_conditions' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_name' => 'required|string|max:255',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'items.*.tax_amount' => 'nullable|numeric|min:0',
        ]);

        $quote = $service->createQuote($data, $data['items']);

        return response()->json([
            'data' => $quote,
            'message' => 'پیش‌فاکتور رسمی فروش با موفقیت ایجاد گردید.',
        ], 201);
    }

    public function convert(Request $request, SalesQuote $quote, OrderToCashService $service): JsonResponse
    {
        $dueDate = $request->input('due_date');
        $invoice = $service->convertQuoteToInvoice($quote, $dueDate);

        return response()->json([
            'data' => $invoice,
            'message' => "پیش‌فاکتور با موفقیت به فاکتور فروش رسمی {$invoice->number} تبدیل شد.",
        ]);
    }

    public function growthAttribution(OrderToCashService $service): JsonResponse
    {
        $report = $service->getGrowthAttributionReport();

        return response()->json($report);
    }
}
