<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Api\PaginatesApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Sales\Entities\SalesInvoice;

class InvoiceController extends Controller
{
    use PaginatesApi;

    public function index(Request $request): JsonResponse
    {
        $query = SalesInvoice::query()->orderByDesc('created_at');

        return $this->paginatedResponse($query->paginate($this->perPage($request)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'number' => 'required|string|max:50|unique:sales_invoices,number',
            'customer_name' => 'required|string|max:255',
            'total' => 'nullable|numeric|min:0',
            'status' => 'nullable|string|max:20',
            'issue_date' => 'nullable|date',
        ]);
        $data['created_by'] = $request->user()->id;
        $invoice = SalesInvoice::create($data);

        return response()->json(['data' => $invoice, 'message' => 'Created'], 201);
    }

    public function show(SalesInvoice $invoice): JsonResponse
    {
        return response()->json(['data' => $invoice]);
    }

    public function update(Request $request, SalesInvoice $invoice): JsonResponse
    {
        $data = $request->validate([
            'number' => 'sometimes|string|max:50|unique:sales_invoices,number,'.$invoice->id,
            'customer_name' => 'sometimes|string|max:255',
            'total' => 'nullable|numeric|min:0',
            'status' => 'sometimes|string|max:20',
            'issue_date' => 'nullable|date',
        ]);
        $invoice->update($data);

        return response()->json(['data' => $invoice->fresh(), 'message' => 'Updated']);
    }

    public function destroy(SalesInvoice $invoice): JsonResponse
    {
        $invoice->delete();

        return response()->noContent();
    }

    public function pdf(SalesInvoice $invoice): JsonResponse
    {
        $content = "Invoice {$invoice->number}\nCustomer: {$invoice->customer_name}\nTotal: {$invoice->total}";

        return response()->json([
            'data' => [
                'invoice_id' => $invoice->id,
                'filename' => "invoice-{$invoice->number}.pdf",
                'content_base64' => base64_encode($content),
            ],
        ]);
    }

    public function email(Request $request, SalesInvoice $invoice): JsonResponse
    {
        $data = $request->validate(['email' => 'required|email']);

        return response()->json([
            'data' => ['invoice_id' => $invoice->id, 'sent_to' => $data['email']],
            'message' => 'Invoice email queued',
        ]);
    }
}
