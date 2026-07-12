<?php

namespace Modules\Projects\Http\Controllers;

use App\Services\PdfGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Projects\Entities\ProInvoice;
use Modules\Projects\Http\Controllers\Concerns\UsesProjectHelpers;

class ProjectInvoiceController extends Controller
{
    use UsesProjectHelpers;

    public function index(Request $request): JsonResponse
    {
        $q = ProInvoice::query()->orderByDesc('id');
        if ($request->filled('status')) {
            $q->where('status', $request->string('status'));
        }
        if ($request->filled('customer_user_id')) {
            $q->where('customer_user_id', (int) $request->input('customer_user_id'));
        }
        if ($request->filled('project_id')) {
            $q->where('project_id', (int) $request->input('project_id'));
        }
        $perPage = min((int) $request->input('per_page', 15), 100);

        return response()->json(['data' => $q->paginate($perPage)]);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->manage($request);
    }

    public function update(Request $request): JsonResponse
    {
        return $this->manage($request);
    }

    public function manage(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'id' => 'nullable|exists:prj_pro_invoices,id',
            'number' => 'nullable|string|max:50',
            'contract_id' => 'nullable|exists:prj_contracts,id',
            'project_id' => 'nullable|exists:prj_projects,id',
            'status' => 'nullable|string|max:50',
            'total' => 'nullable|numeric',
            'items' => 'nullable|array',
            'discount' => 'nullable|numeric',
            'notes' => 'nullable|string',
            'customer_user_id' => 'nullable|exists:users,id',
        ]);
        if (! empty($payload['id'])) {
            $inv = ProInvoice::query()->findOrFail($payload['id']);
            $inv->update(collect($payload)->except('id')->all());
        } else {
            $payload['created_by'] = $request->user()->id;
            $inv = ProInvoice::query()->create($payload);
        }

        return response()->json(['data' => $inv], empty($payload['id']) ? 201 : 200);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => ProInvoice::query()->findOrFail($id)]);
    }

    public function destroy(int $id): JsonResponse
    {
        ProInvoice::query()->whereKey($id)->delete();

        return response()->json([], 204);
    }

    public function pdf(Request $request, int $id): JsonResponse
    {
        $invoice = ProInvoice::query()->findOrFail($id);
        $html = view('pdf.pro-invoice', ['invoice' => $invoice])->render();
        $binary = app(PdfGeneratorService::class)->htmlToPdf($html);
        if ($binary === null) {
            return response()->json([
                'data' => [
                    'invoice_id' => $id,
                    'url' => null,
                    'message' => 'PDF unavailable: install barryvdh/laravel-dompdf and PHP ext-dom.',
                ],
            ]);
        }
        $path = 'invoices/invoice-'.$id.'-'.Str::random(6).'.pdf';
        Storage::disk('public')->put($path, $binary);
        $downloadToken = $this->registerPdfDownloadToken($path, 'public');

        return response()->json([
            'data' => [
                'invoice_id' => $id,
                'url' => Storage::disk('public')->url($path),
                'path' => $path,
                'download_token' => $downloadToken,
            ],
        ]);
    }

    public function sendEmail(Request $request, int $id): JsonResponse
    {
        $invoice = ProInvoice::query()->findOrFail($id);
        $data = $request->validate(['to' => 'required|email']);
        $htmlPdf = view('pdf.pro-invoice', ['invoice' => $invoice])->render();
        $binary = app(PdfGeneratorService::class)->htmlToPdf($htmlPdf);
        try {
            Mail::send('emails.invoice-plain', [
                'number' => $invoice->number ?? (string) $invoice->id,
                'total' => $invoice->total,
            ], function ($message) use ($data, $invoice, $binary) {
                $message->to($data['to'])
                    ->subject('فاکتور: '.($invoice->number ?? $invoice->id));
                if ($binary !== null) {
                    $message->attachData($binary, 'invoice-'.$invoice->id.'.pdf', ['mime' => 'application/pdf']);
                }
            });

            return response()->json(['data' => ['sent' => true, 'had_pdf_attachment' => $binary !== null]]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'Mail failed: '.$e->getMessage()], 422);
        }
    }
}
