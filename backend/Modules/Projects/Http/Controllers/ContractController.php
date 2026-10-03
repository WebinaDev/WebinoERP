<?php

namespace Modules\Projects\Http\Controllers;

use App\Mail\ContractDocumentMail;
use App\Services\PdfGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Support\CustomerAccess;
use Modules\Projects\Entities\CatalogProduct;
use Modules\Projects\Entities\Contract;
use Modules\Projects\Entities\ProjectTask;
use Modules\Projects\Http\Controllers\Concerns\UsesProjectHelpers;

class ContractController extends Controller
{
    use UsesProjectHelpers;

    public function index(Request $request, CustomerAccess $access): JsonResponse
    {
        $query = Contract::query()->orderByDesc('created_at');
        $access->scopeContracts($query, $request->user());
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        $perPage = min((int) $request->input('per_page', 15), 100);

        return response()->json($query->paginate($perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'project_id' => 'nullable|exists:prj_projects,id',
            'status' => 'nullable|string|max:50',
            'amount' => 'nullable|numeric',
            'customer_account_id' => 'nullable|exists:crm_accounts,id',
            'lead_id' => 'nullable|exists:crm_leads,id',
            'installments_data' => 'nullable|array',
            'notes' => 'nullable|string',
        ]);
        $data['created_by'] = $request->user()->id;
        $data['status'] = $data['status'] ?? 'draft';
        $c = Contract::query()->create($data);

        return response()->json(['data' => $c], 201);
    }

    public function show(Request $request, int $id, CustomerAccess $access): JsonResponse
    {
        return $this->details($request, $id, $access);
    }

    public function update(Request $request, int $id, CustomerAccess $access): JsonResponse
    {
        abort_if($access->isPortalCustomer($request->user()), 403);
        $c = $this->visibleContract($request, $id, $access);
        $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'status' => 'nullable|string|max:50',
            'amount' => 'nullable|numeric',
            'installments_data' => 'nullable|array',
            'notes' => 'nullable|string',
        ]);
        $c->update($data);

        return response()->json(['data' => $c->fresh()]);
    }

    public function destroy(Request $request, int $id, CustomerAccess $access): JsonResponse
    {
        abort_if($access->isPortalCustomer($request->user()), 403);
        $this->visibleContract($request, $id, $access)->delete();

        return response()->json([], 204);
    }

    public function cancel(Request $request, int $id, CustomerAccess $access): JsonResponse
    {
        abort_if($access->isPortalCustomer($request->user()), 403);
        $c = $this->visibleContract($request, $id, $access);
        $c->update(['status' => 'cancelled']);

        return response()->json(['data' => ['id' => $id, 'cancelled' => true]]);
    }

    public function addProject(Request $request, int $id, CustomerAccess $access): JsonResponse
    {
        abort_if($access->isPortalCustomer($request->user()), 403);
        $data = $request->validate(['project_id' => 'required|exists:prj_projects,id']);
        $c = $this->visibleContract($request, $id, $access);
        $c->update(['project_id' => $data['project_id']]);

        return response()->json(['data' => ['contract_id' => $id, 'project_id' => $data['project_id']]], 201);
    }

    public function addServicesFromProduct(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => 'required|exists:prj_products,id',
            'project_id' => 'nullable|exists:prj_projects,id',
            'contract_id' => 'nullable|exists:prj_contracts,id',
        ]);
        $product = CatalogProduct::query()->findOrFail($data['product_id']);
        $projectId = $data['project_id'] ?? null;
        if (! $projectId && ! empty($data['contract_id'])) {
            $projectId = Contract::query()->findOrFail($data['contract_id'])->project_id;
        }
        if (! $projectId) {
            return response()->json(['message' => 'project_id or contract_id with linked project is required'], 422);
        }
        $raw = $product->task_template ?? [];
        $titles = [];
        if (is_array($raw)) {
            if (isset($raw['tasks']) && is_array($raw['tasks'])) {
                foreach ($raw['tasks'] as $t) {
                    $titles[] = is_string($t) ? $t : ($t['title'] ?? null);
                }
            } elseif (isset($raw['titles']) && is_array($raw['titles'])) {
                $titles = $raw['titles'];
            } else {
                foreach ($raw as $t) {
                    if (is_string($t)) {
                        $titles[] = $t;
                    } elseif (is_array($t) && isset($t['title'])) {
                        $titles[] = $t['title'];
                    }
                }
            }
        }
        $titles = array_values(array_filter($titles, fn ($t) => is_string($t) && $t !== ''));
        if ($titles === []) {
            $titles = ['خدمت: '.$product->name];
        }
        $ws = $this->defaultWorkflowStatusId();
        $created = [];
        foreach ($titles as $title) {
            $created[] = ProjectTask::query()->create([
                'project_id' => $projectId,
                'title' => $title,
                'status' => 'open',
                'workflow_status_id' => $ws,
                'created_by' => $request->user()->id,
            ]);
        }

        return response()->json(['data' => ['tasks' => $created]], 201);
    }

    public function details(Request $request, int $id, CustomerAccess $access): JsonResponse
    {
        $c = $this->visibleContract($request, $id, $access);
        $c->load(['installments', 'lead']);

        return response()->json(['data' => $c]);
    }

    public function pdf(Request $request, int $id, CustomerAccess $access): JsonResponse
    {
        $contract = $this->visibleContract($request, $id, $access);
        $contract->load(['installments', 'lead', 'project']);
        $html = view('pdf.contract', ['contract' => $contract])->render();
        $binary = app(PdfGeneratorService::class)->htmlToPdf($html);
        if ($binary === null) {
            return response()->json([
                'data' => [
                    'contract_id' => $id,
                    'url' => null,
                    'message' => 'PDF unavailable: install barryvdh/laravel-dompdf and PHP ext-dom.',
                ],
            ]);
        }
        $path = 'contracts/contract-'.$id.'-'.Str::random(6).'.pdf';
        Storage::disk('public')->put($path, $binary);
        $downloadToken = $this->registerPdfDownloadToken($path, 'public');

        return response()->json([
            'data' => [
                'contract_id' => $id,
                'url' => Storage::disk('public')->url($path),
                'path' => $path,
                'download_token' => $downloadToken,
            ],
        ]);
    }

    public function email(Request $request, int $id, CustomerAccess $access): JsonResponse
    {
        abort_if($access->isPortalCustomer($request->user()), 403);
        $contract = $this->visibleContract($request, $id, $access);
        $contract->load(['installments', 'lead', 'project']);
        $data = $request->validate(['to' => 'required|email']);
        $html = view('pdf.contract', ['contract' => $contract])->render();
        $binary = app(PdfGeneratorService::class)->htmlToPdf($html);
        try {
            Mail::to($data['to'])->send(new ContractDocumentMail(
                $contract->title ?? 'قرارداد',
                $binary,
                'contract-'.$contract->id.'.pdf'
            ));

            return response()->json(['data' => ['sent' => true, 'had_pdf_attachment' => $binary !== null]]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'Mail failed: '.$e->getMessage()], 422);
        }
    }

    private function visibleContract(Request $request, int $id, CustomerAccess $access): Contract
    {
        $query = Contract::query()->whereKey($id);
        $access->scopeContracts($query, $request->user());

        return $query->firstOrFail();
    }
}
