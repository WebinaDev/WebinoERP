<?php

namespace Modules\Org\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Accounting\Entities\AccFiscalYear;
use Modules\Accounting\Entities\AccJournalEntry;
use Modules\Crm\Entities\CrmCompany;
use Modules\Org\Entities\OrgBranch;
use Modules\Org\Entities\OrgDocumentSequence;
use Modules\Org\Entities\OrgTransfer;
use Modules\Org\Services\DocumentNumberingService;
use Modules\Org\Services\OrgContextService;

class OrgController extends Controller
{
    public function companies(Request $request): JsonResponse
    {
        $query = CrmCompany::query();
        if (! $request->user()?->hasRole('system_manager')) {
            $companyIds = DB::table('crm_company_users')
                ->where('user_id', $request->user()->id)
                ->where('is_active', true)
                ->pluck('company_id');
            $query->whereIn('id', $companyIds);
        }
        $companies = $query->orderBy('name')->get();

        return response()->json(['data' => $companies]);
    }

    public function storeCompany(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:160',
            'legal_name' => 'nullable|string|max:200',
            'national_id' => 'nullable|string|max:20',
            'economic_code' => 'nullable|string|max:20',
            'currency_code' => 'nullable|string|max:8',
            'is_default' => 'nullable|boolean',
        ]);

        if (! empty($data['is_default'])) {
            CrmCompany::query()->update(['is_default' => false]);
        }

        $user = $request->user();
        $company = DB::transaction(function () use ($data, $user): CrmCompany {
            $company = CrmCompany::query()->create($data);

            // The creator must be able to work inside the company they just created
            // (context switch and authz both require an active membership).
            if ($user && Schema::hasTable('crm_company_users')) {
                $hasActive = DB::table('crm_company_users')->where('user_id', $user->id)->where('is_active', true)->exists();
                DB::table('crm_company_users')->updateOrInsert(
                    ['user_id' => $user->id, 'company_id' => $company->id],
                    ['is_active' => ! $hasActive, 'created_at' => now(), 'updated_at' => now()]
                );
                if (Schema::hasTable('authz_memberships')) {
                    $hasDefault = DB::table('authz_memberships')->where('user_id', $user->id)->where('is_default_context', true)->exists();
                    DB::table('authz_memberships')->updateOrInsert(
                        ['user_id' => $user->id, 'company_id' => $company->id, 'branch_id' => null],
                        ['status' => 'active', 'is_default_context' => ! $hasDefault, 'created_at' => now(), 'updated_at' => now()]
                    );
                }
            }

            return $company;
        });

        return response()->json(['data' => $company], 201);
    }

    public function branches(Request $request): JsonResponse
    {
        $query = OrgBranch::query()->with(['company', 'manager']);
        if ($request->filled('company_id')) {
            $query->where('company_id', $request->input('company_id'));
        }

        return response()->json(['data' => $query->orderBy('name')->get()]);
    }

    public function storeBranch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => 'required|exists:crm_companies,id',
            'code' => 'nullable|string|max:32',
            'name' => 'required|string|max:160',
            'type' => 'nullable|string|max:32',
            'phone' => 'nullable|string|max:32',
            'address' => 'nullable|string',
            'manager_id' => 'nullable|exists:users,id',
            'default_warehouse_id' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $branch = OrgBranch::query()->create($data);

        return response()->json(['data' => $branch->load('company', 'manager')], 201);
    }

    public function activeContext(Request $request, OrgContextService $orgService): JsonResponse
    {
        $user = $request->user();
        $company = $orgService->getActiveCompany($user);
        $branch = $orgService->getActiveBranch($user, $company?->id);

        return response()->json([
            'data' => [
                'company' => $company,
                'branch' => $branch,
            ],
        ]);
    }

    public function switchContext(Request $request, OrgContextService $orgService): JsonResponse
    {
        $data = $request->validate([
            'company_id' => 'required|exists:crm_companies,id',
            'branch_id' => 'nullable|exists:org_branches,id',
        ]);

        $result = $orgService->switchContext($request->user(), (int) $data['company_id'], ! empty($data['branch_id']) ? (int) $data['branch_id'] : null);

        return response()->json(['data' => $result]);
    }

    public function sequences(Request $request): JsonResponse
    {
        $query = OrgDocumentSequence::query()->with(['company', 'branch']);
        if ($request->filled('company_id')) {
            $query->where('company_id', $request->input('company_id'));
        }

        return response()->json(['data' => $query->get()]);
    }

    public function storeSequence(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => 'required|exists:crm_companies,id',
            'branch_id' => 'nullable|exists:org_branches,id',
            'document_type' => 'required|string|max:48',
            'prefix' => 'nullable|string|max:16',
            'next_number' => 'nullable|integer|min:1',
            'padding_zeros' => 'nullable|integer|min:1|max:12',
        ]);

        $seq = OrgDocumentSequence::query()->updateOrCreate(
            [
                'company_id' => $data['company_id'],
                'branch_id' => $data['branch_id'] ?? null,
                'document_type' => $data['document_type'],
            ],
            [
                'prefix' => $data['prefix'] ?? '',
                'next_number' => $data['next_number'] ?? 1,
                'padding_zeros' => $data['padding_zeros'] ?? 5,
            ]
        );

        return response()->json(['data' => $seq], 201);
    }

    public function nextNumber(Request $request, DocumentNumberingService $numbering): JsonResponse
    {
        $data = $request->validate([
            'company_id' => 'required|exists:crm_companies,id',
            'branch_id' => 'nullable|exists:org_branches,id',
            'document_type' => 'required|string|max:48',
            'prefix' => 'nullable|string|max:16',
        ]);

        $num = $numbering->generateNextNumber(
            (int) $data['company_id'],
            ! empty($data['branch_id']) ? (int) $data['branch_id'] : null,
            $data['document_type'],
            $data['prefix'] ?? 'DOC-'
        );

        return response()->json(['data' => ['number' => $num]]);
    }

    public function transfers(Request $request): JsonResponse
    {
        $query = OrgTransfer::query()->with(['fromCompany', 'toCompany', 'fromBranch', 'toBranch', 'creator']);
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('company_id')) {
            $cid = $request->input('company_id');
            $query->where(fn ($q) => $q->where('from_company_id', $cid)->orWhere('to_company_id', $cid));
        }

        return response()->json(['data' => $query->orderByDesc('id')->paginate(30)]);
    }

    public function storeTransfer(Request $request, DocumentNumberingService $numbering): JsonResponse
    {
        $data = $request->validate([
            'type' => 'required|in:inter_branch,inter_company',
            'from_company_id' => 'required|exists:crm_companies,id',
            'to_company_id' => 'required|exists:crm_companies,id',
            'from_branch_id' => 'nullable|exists:org_branches,id',
            'to_branch_id' => 'nullable|exists:org_branches,id',
            'from_warehouse_id' => 'nullable|integer',
            'to_warehouse_id' => 'nullable|integer',
            'transfer_date' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.qty' => 'required|numeric|gt:0',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $num = $numbering->generateNextNumber(
            (int) $data['from_company_id'],
            ! empty($data['from_branch_id']) ? (int) $data['from_branch_id'] : null,
            'transfer_voucher',
            'TRF-'
        );

        $total = 0;
        foreach ($data['items'] as $item) {
            $total += ((float) $item['qty']) * ((float) ($item['unit_price'] ?? 0));
        }

        $transfer = OrgTransfer::query()->create([
            'transfer_number' => $num,
            'type' => $data['type'],
            'from_company_id' => $data['from_company_id'],
            'to_company_id' => $data['to_company_id'],
            'from_branch_id' => $data['from_branch_id'] ?? null,
            'to_branch_id' => $data['to_branch_id'] ?? null,
            'from_warehouse_id' => $data['from_warehouse_id'] ?? null,
            'to_warehouse_id' => $data['to_warehouse_id'] ?? null,
            'transfer_date' => $data['transfer_date'] ?? now()->toDateString(),
            'status' => 'draft',
            'items' => $data['items'],
            'total_amount' => $total,
            'notes' => $data['notes'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $transfer->load('fromCompany', 'toCompany', 'fromBranch', 'toBranch')], 201);
    }

    public function receiveTransfer(Request $request, int $id): JsonResponse
    {
        $transfer = OrgTransfer::query()->findOrFail($id);
        if ($transfer->status === 'received') {
            return response()->json(['message' => __('messages.action_successful')], 200);
        }

        DB::transaction(function () use ($transfer, $request) {
            $transfer->update([
                'status' => 'received',
                'received_by' => $request->user()->id,
                'received_at' => now(),
            ]);

            // If inter-company and Accounting module exists, create balancing Journal Entry
            if ($transfer->type === 'inter_company' && class_exists(AccJournalEntry::class) && Schema::hasTable('acc_journal_entries')) {
                $fy = AccFiscalYear::query()->where('is_closed', false)->latest('id')->first();
                if ($fy) {
                    $entry = AccJournalEntry::query()->create([
                        'fiscal_year_id' => $fy->id,
                        'document_no' => $transfer->transfer_number,
                        'document_date' => $transfer->transfer_date,
                        'description' => "Inter-company transfer {$transfer->transfer_number} from Co #{$transfer->from_company_id} to Co #{$transfer->to_company_id}",
                        'status' => 'posted',
                        'created_by' => $request->user()->id,
                    ]);
                    $transfer->update(['journal_entry_id' => $entry->id]);
                }
            }
        });

        return response()->json(['data' => $transfer->fresh()]);
    }

    public function consolidatedReport(Request $request): JsonResponse
    {
        $companies = CrmCompany::query()->get();
        $summary = [];

        foreach ($companies as $co) {
            $branchesCount = Schema::hasTable('org_branches') ? OrgBranch::query()->where('company_id', $co->id)->count() : 0;
            $journalEntriesCount = Schema::hasTable('acc_journal_entries') ? DB::table('acc_journal_entries')->where('company_id', $co->id)->count() : 0;
            $salesTotal = Schema::hasTable('sales_invoices') ? (float) DB::table('sales_invoices')->where('company_id', $co->id)->sum('total') : 0.0;

            $summary[] = [
                'company_id' => $co->id,
                'company_name' => $co->name,
                'currency' => $co->currency_code ?? 'IRR',
                'branches_count' => $branchesCount,
                'journal_entries_count' => $journalEntriesCount,
                'sales_total' => $salesTotal,
            ];
        }

        $holdingTotalSales = array_sum(array_column($summary, 'sales_total'));
        $holdingTotalBranches = array_sum(array_column($summary, 'branches_count'));

        return response()->json([
            'data' => [
                'holding_summary' => [
                    'companies_count' => count($companies),
                    'total_branches' => $holdingTotalBranches,
                    'total_sales' => $holdingTotalSales,
                ],
                'companies' => $summary,
            ],
        ]);
    }
}
