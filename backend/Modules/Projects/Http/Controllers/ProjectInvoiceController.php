<?php

namespace Modules\Projects\Http\Controllers;

use App\Services\PdfGeneratorService;
use App\Support\CustomerAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Crm\Entities\CrmAccount;
use Modules\Crm\Entities\CrmActivity;
use Modules\Projects\Entities\ProInvoice;
use Modules\Projects\Entities\Project;
use Modules\Projects\Http\Controllers\Concerns\UsesProjectHelpers;

class ProjectInvoiceController extends Controller
{
    use UsesProjectHelpers;

    public function index(Request $request, CustomerAccess $access): JsonResponse
    {
        $q = ProInvoice::query()->orderByDesc('id');
        $access->scopeInvoices($q, $request->user());
        if ($request->filled('status')) {
            $q->where('status', $request->string('status'));
        }
        if ($request->filled('customer_user_id')) {
            $q->where('customer_user_id', (int) $request->input('customer_user_id'));
        }
        if ($request->filled('project_id')) {
            $q->where('project_id', (int) $request->input('project_id'));
        }
        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
        $paginator = $q->paginate($perPage);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function store(Request $request, CustomerAccess $access): JsonResponse
    {
        abort_if($access->isPortalCustomer($request->user()), 403);

        return $this->manage($request, $access);
    }

    public function update(Request $request, CustomerAccess $access): JsonResponse
    {
        abort_if($access->isPortalCustomer($request->user()), 403);

        return $this->manage($request, $access);
    }

    public function manage(Request $request, CustomerAccess $access): JsonResponse
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
        $creating = empty($payload['id']);
        if (! $creating) {
            $inv = $this->visibleInvoice($request, (int) $payload['id'], $access);
            $inv->update(collect($payload)->except('id')->all());
        } else {
            $payload['created_by'] = $request->user()->id;
            $inv = ProInvoice::query()->create(collect($payload)->except('id')->all());
        }
        $this->syncPaidInvoice($inv->fresh(), $request);

        return response()->json(['data' => $inv->fresh()], $creating ? 201 : 200);
    }

    public function show(Request $request, int $id, CustomerAccess $access): JsonResponse
    {
        return response()->json(['data' => $this->visibleInvoice($request, $id, $access)]);
    }

    public function destroy(Request $request, int $id, CustomerAccess $access): JsonResponse
    {
        abort_if($access->isPortalCustomer($request->user()), 403);
        $this->visibleInvoice($request, $id, $access)->delete();

        return response()->json([], 204);
    }

    public function pdf(Request $request, int $id, CustomerAccess $access): JsonResponse
    {
        $invoice = $this->visibleInvoice($request, $id, $access);
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

    public function sendEmail(Request $request, int $id, CustomerAccess $access): JsonResponse
    {
        abort_if($access->isPortalCustomer($request->user()), 403);
        $invoice = $this->visibleInvoice($request, $id, $access);
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

    private function visibleInvoice(Request $request, int $id, CustomerAccess $access): ProInvoice
    {
        $query = ProInvoice::query()->whereKey($id);
        $access->scopeInvoices($query, $request->user());

        return $query->firstOrFail();
    }

    private function syncPaidInvoice(ProInvoice $invoice, Request $request): void
    {
        if ((string) $invoice->status !== 'paid' || ! $invoice->project_id || ! $request->user()) {
            return;
        }
        $project = Project::query()->find($invoice->project_id);
        if (! $project?->customer_account_id) {
            return;
        }
        $subject = 'Invoice #'.$invoice->id.' paid';
        $exists = CrmActivity::query()
            ->where('related_model', CrmAccount::class)
            ->where('related_id', $project->customer_account_id)
            ->where('subject', $subject)
            ->exists();
        if ($exists) {
            return;
        }
        CrmActivity::query()->create([
            'type' => 'invoice_paid',
            'subject' => $subject,
            'description' => 'Project invoice marked paid.',
            'related_model' => CrmAccount::class,
            'related_id' => $project->customer_account_id,
            'outcome' => 'Success',
            'completed_at' => now(),
            'created_by' => $request->user()->id,
        ]);
    }
}
