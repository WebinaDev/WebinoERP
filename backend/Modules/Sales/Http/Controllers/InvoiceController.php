<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Api\PaginatesApi;
use App\Mail\SalesInvoiceMail;
use App\Services\PdfGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Modules\Crm\Entities\CrmAccount;
use Modules\Projects\Entities\Project;
use Modules\Sales\Entities\SalesInvoice;

class InvoiceController extends Controller
{
    use PaginatesApi;

    public function index(Request $request): JsonResponse
    {
        $query = SalesInvoice::query()->orderByDesc('created_at');
        $response = $this->paginatedResponse($query->paginate($this->perPage($request)));

        $customers = [];
        if (class_exists(CrmAccount::class)) {
            $customers = CrmAccount::query()
                ->orderBy('name')
                ->limit(500)
                ->get(['id', 'name'])
                ->map(fn (CrmAccount $a) => [
                    'id' => $a->id,
                    'display_name' => $a->name,
                    'name' => $a->name,
                ])
                ->all();
        }

        $payload = $response->getData(true);
        $payload['meta']['customers'] = $customers;
        $payload['customers'] = $customers;

        return response()->json($payload);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedPayload($request);
        $data['created_by'] = $request->user()->id;
        $this->applyComputedTotals($data);
        $this->resolveCustomerAndProject($data);
        if (empty($data['number']) && empty($data['invoice_number'])) {
            $data['number'] = $this->nextInvoiceNumber();
            $data['invoice_number'] = $data['number'];
        } else {
            $data['number'] = $data['invoice_number'] ?? $data['number'];
            $data['invoice_number'] = $data['number'];
        }
        $invoice = SalesInvoice::create($data);

        return response()->json(['data' => $invoice->fresh(), 'message' => 'Created'], 201);
    }

    public function show(SalesInvoice $invoice): JsonResponse
    {
        return response()->json(['data' => $invoice]);
    }

    public function update(Request $request, SalesInvoice $invoice): JsonResponse
    {
        $data = $this->validatedPayload($request, $invoice);
        $this->applyComputedTotals($data);
        $this->resolveCustomerAndProject($data);
        if (isset($data['invoice_number']) || isset($data['number'])) {
            $data['number'] = $data['invoice_number'] ?? $data['number'] ?? $invoice->number;
            $data['invoice_number'] = $data['number'];
        }
        $invoice->update($data);

        return response()->json(['data' => $invoice->fresh(), 'message' => 'Updated']);
    }

    public function destroy(SalesInvoice $invoice): JsonResponse
    {
        $invoice->delete();

        return response()->noContent();
    }

    public function projectsForCustomer(Request $request): JsonResponse
    {
        $customerId = (int) $request->input('customer_id', 0);
        if ($customerId <= 0 || ! class_exists(Project::class)) {
            return response()->json(['data' => []]);
        }

        $projects = Project::query()
            ->where('customer_account_id', $customerId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Project $p) => ['id' => $p->id, 'title' => $p->name, 'name' => $p->name]);

        return response()->json(['data' => $projects]);
    }

    public function pdf(SalesInvoice $invoice): JsonResponse
    {
        $html = view('pdf.sales-invoice', ['invoice' => $invoice])->render();
        $binary = app(PdfGeneratorService::class)->htmlToPdf($html);
        if ($binary === null) {
            $binary = $this->minimalPdfFallback($invoice);
        }

        return response()->json([
            'data' => [
                'invoice_id' => $invoice->id,
                'filename' => 'invoice-'.($invoice->invoice_number ?: $invoice->number).'.pdf',
                'content_base64' => base64_encode($binary),
                'mime' => 'application/pdf',
            ],
        ]);
    }

    public function email(Request $request, SalesInvoice $invoice): JsonResponse
    {
        $data = $request->validate(['email' => 'required|email']);
        $html = view('pdf.sales-invoice', ['invoice' => $invoice])->render();
        $binary = app(PdfGeneratorService::class)->htmlToPdf($html);
        if ($binary === null) {
            $binary = $this->minimalPdfFallback($invoice);
        }
        $number = $invoice->invoice_number ?: $invoice->number;

        try {
            Mail::to($data['email'])->send(new SalesInvoiceMail(
                $number,
                $invoice->total,
                $binary,
                'invoice-'.$number.'.pdf',
            ));
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'Mail failed: '.$e->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                'invoice_id' => $invoice->id,
                'sent_to' => $data['email'],
                'had_pdf_attachment' => true,
            ],
            'message' => 'Invoice email sent',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request, ?SalesInvoice $existing = null): array
    {
        $numberRule = $existing
            ? 'nullable|string|max:50|unique:sales_invoices,number,'.$existing->id
            : 'nullable|string|max:50|unique:sales_invoices,number';
        $invoiceNumberRule = $existing
            ? 'nullable|string|max:50|unique:sales_invoices,invoice_number,'.$existing->id
            : 'nullable|string|max:50|unique:sales_invoices,invoice_number';

        return $request->validate([
            'number' => $numberRule,
            'invoice_number' => $invoiceNumberRule,
            'customer_name' => 'nullable|string|max:255',
            'customer_id' => 'nullable|integer|min:1',
            'project_id' => 'nullable|integer|min:1',
            'project_title' => 'nullable|string|max:255',
            'total' => 'nullable|numeric|min:0',
            'subtotal' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'status' => 'nullable|string|max:20',
            'issue_date' => 'nullable|date',
            'payment_method' => 'nullable|string',
            'items' => 'nullable|array',
            'items.*.title' => 'nullable|string|max:255',
            'items.*.desc' => 'nullable|string|max:1000',
            'items.*.price' => 'nullable|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyComputedTotals(array &$data): void
    {
        if (! isset($data['items']) || ! is_array($data['items'])) {
            return;
        }

        $subtotal = 0.0;
        $discount = 0.0;
        foreach ($data['items'] as $item) {
            if (! is_array($item)) {
                continue;
            }
            $subtotal += (float) ($item['price'] ?? 0);
            $discount += (float) ($item['discount'] ?? 0);
        }
        $data['subtotal'] = $subtotal;
        $data['discount'] = $discount;
        $data['total'] = max(0, $subtotal - $discount);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveCustomerAndProject(array &$data): void
    {
        if (! empty($data['customer_id']) && class_exists(CrmAccount::class) && empty($data['customer_name'])) {
            $account = CrmAccount::query()->find($data['customer_id']);
            if ($account) {
                $data['customer_name'] = $account->name;
            }
        }

        if (! empty($data['project_id']) && class_exists(Project::class)) {
            $project = Project::query()->find($data['project_id']);
            if ($project) {
                $data['project_title'] = $project->name;
            }
        }

        if (empty($data['customer_name'])) {
            $data['customer_name'] = '—';
        }
    }

    private function nextInvoiceNumber(): string
    {
        $prefix = 'INV-'.now()->format('Ymd').'-';
        $latest = SalesInvoice::query()
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('number');
        $seq = 1;
        if (is_string($latest) && preg_match('/(\d+)$/', $latest, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return $prefix.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }

    private function minimalPdfFallback(SalesInvoice $invoice): string
    {
        $number = $invoice->invoice_number ?: $invoice->number;
        $lines = [
            'Invoice: '.$number,
            'Customer: '.$invoice->customer_name,
            'Project: '.($invoice->project_title ?: '—'),
            'Issue date: '.($invoice->issue_date?->format('Y-m-d') ?: '—'),
            'Subtotal: '.$invoice->subtotal,
            'Discount: '.$invoice->discount,
            'Total: '.$invoice->total,
            'Payment: '.($invoice->payment_method ?: '—'),
        ];
        foreach ($invoice->items ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }
            $lines[] = sprintf(
                '- %s | %s | %s | disc %s',
                $item['title'] ?? '',
                $item['desc'] ?? '',
                $item['price'] ?? 0,
                $item['discount'] ?? 0
            );
        }
        $text = implode("\n", $lines);
        // Escape for PDF literal string
        $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
        $stream = "BT /F1 10 Tf 40 750 Td 14 TL (".str_replace("\n", ') Tj T* (', $escaped).") Tj ET";
        $len = strlen($stream);

        return "%PDF-1.4\n".
            "1 0 obj<< /Type /Catalog /Pages 2 0 R >>endobj\n".
            "2 0 obj<< /Type /Pages /Kids [3 0 R] /Count 1 >>endobj\n".
            "3 0 obj<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources<< /Font<< /F1 5 0 R >> >> >>endobj\n".
            "4 0 obj<< /Length {$len} >>stream\n{$stream}\nendstream endobj\n".
            "5 0 obj<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>endobj\n".
            "xref\n0 6\n0000000000 65535 f \n".
            "trailer<< /Size 6 /Root 1 0 R >>\nstartxref\n0\n%%EOF\n".
            '%%'.Str::random(8);
    }
}
