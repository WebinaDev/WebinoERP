<?php

namespace Modules\Crm\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Modules\Core\Database\Seeders\RolesAndPermissionsSeeder;
use Modules\Crm\Entities\CrmAccount;
use Modules\Crm\Entities\CrmConsultation;
use Modules\Crm\Entities\CrmLead;
use Modules\Crm\Entities\CrmStatus;
use Modules\Crm\Services\CrmAutomationDispatcher;
use Modules\Projects\Entities\Contract;
use Modules\Projects\Entities\Project;

class CrmParityController extends Controller
{
    public function leadStatuses(): JsonResponse
    {
        return response()->json(['data' => CrmStatus::query()->orderBy('sort_order')->get()]);
    }

    public function storeLeadStatus(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'color' => 'required|string|max:7',
            'sort_order' => 'nullable|integer|min:0',
        ]);
        $row = CrmStatus::query()->create($data + ['is_active' => true]);

        return response()->json(['data' => $row], 201);
    }

    public function deleteLeadStatus(int $id): JsonResponse
    {
        CrmStatus::query()->whereKey($id)->delete();

        return response()->json([], 204);
    }

    public function changeLeadStatus(Request $request, int $id, CrmAutomationDispatcher $automation): JsonResponse
    {
        $lead = CrmLead::query()->findOrFail($id);
        $data = $request->validate(['status_id' => 'required|exists:crm_statuses,id']);
        $previous = (int) $lead->status_id;
        $lead->update($data);
        $fresh = $lead->fresh('status');
        $automation->leadStatusChanged($fresh, $previous);

        return response()->json(['data' => $fresh]);
    }

    public function assignLead(Request $request, int $id): JsonResponse
    {
        $lead = CrmLead::query()->findOrFail($id);
        $data = $request->validate(['assigned_to' => 'nullable|exists:users,id']);
        $lead->update($data);

        return response()->json(['data' => $lead->fresh('assignedTo')]);
    }

    public function leadAssignees(): JsonResponse
    {
        $users = User::role([
            RolesAndPermissionsSeeder::ROLE_SALES_CONSULTANT,
            RolesAndPermissionsSeeder::ROLE_SYSTEM_MANAGER,
        ])->get(['id', 'name', 'email']);

        return response()->json(['data' => $users]);
    }

    public function leadForContract(int $id): JsonResponse
    {
        $lead = CrmLead::query()->with(['status', 'source'])->findOrFail($id);

        return response()->json([
            'data' => $lead,
            'existing_customer_id' => $lead->converted_to_account_id,
        ]);
    }

    public function convertLead(int $id): JsonResponse
    {
        $lead = CrmLead::query()->findOrFail($id);
        $account = CrmAccount::query()->create([
            'name' => $lead->company ?: ($lead->first_name.' '.$lead->last_name),
            'type' => 'customer',
            'owner_id' => $lead->assigned_to,
            'created_by' => auth()->id(),
        ]);
        $lead->update([
            'converted_at' => now(),
            'converted_to_account_id' => $account->id,
        ]);

        return response()->json(['data' => ['account_id' => $account->id, 'account' => $account]]);
    }

    public function exportLeads(): StreamedResponse
    {
        $filename = 'leads-'.now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'topic', 'first_name', 'last_name', 'mobile', 'email', 'status_id', 'created_at']);

            CrmLead::query()->orderBy('id')->chunk(500, function ($chunk) use ($out) {
                foreach ($chunk as $lead) {
                    fputcsv($out, [
                        $lead->id,
                        $lead->topic,
                        $lead->first_name,
                        $lead->last_name,
                        $lead->mobile,
                        $lead->email,
                        $lead->status_id,
                        optional($lead->created_at)->toIso8601String(),
                    ]);
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function importLeads(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:5120']);
        $defaultStatus = CrmStatus::query()->orderBy('sort_order')->value('id');
        if (! $defaultStatus) {
            return response()->json(['message' => 'No CRM status configured'], 422);
        }

        $path = $request->file('file')->getRealPath();
        $imported = 0;
        if ($path && ($h = fopen($path, 'r')) !== false) {
            fgetcsv($h);
            while (($row = fgetcsv($h)) !== false) {
                if (count($row) < 4) {
                    continue;
                }
                CrmLead::query()->create([
                    'topic' => $row[0] ?? 'import',
                    'first_name' => $row[1] ?? '',
                    'last_name' => $row[2] ?? '',
                    'mobile' => $row[3] ?? '0000000000',
                    'status_id' => $defaultStatus,
                    'created_by' => auth()->id(),
                ]);
                $imported++;
            }
            fclose($h);
        }

        return response()->json(['data' => ['imported' => $imported]]);
    }

    public function accountsSummary(): JsonResponse
    {
        return response()->json([
            'data' => [
                'total' => CrmAccount::query()->count(),
                'individual' => CrmAccount::query()->where('type', 'individual')->count(),
                'company' => CrmAccount::query()->where('type', 'company')->count(),
            ],
        ]);
    }

    public function accountsIndex(Request $request): JsonResponse
    {
        $q = CrmAccount::query()->orderByDesc('id');
        if ($request->filled('search')) {
            $s = '%'.$request->string('search').'%';
            $q->where('name', 'like', $s);
        }
        if ($request->filled('type')) {
            $q->where('type', $request->string('type'));
        }

        return response()->json(['data' => $q->paginate(min((int) $request->input('per_page', 25), 100))]);
    }

    public function storeAccount(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'nullable|string|max:50',
            'account_code' => 'nullable|string|max:100',
            'website' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'owner_id' => 'nullable|exists:users,id',
        ]);
        $data['created_by'] = $request->user()->id;
        if (empty($data['type'])) {
            $data['type'] = 'customer';
        }
        $a = CrmAccount::query()->create($data);

        return response()->json(['data' => $a], 201);
    }

    public function updateAccount(Request $request, int $id): JsonResponse
    {
        $a = CrmAccount::query()->findOrFail($id);
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'type' => 'nullable|string|max:50',
            'account_code' => 'nullable|string|max:100',
            'website' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'owner_id' => 'nullable|exists:users,id',
        ]);
        $a->update($data);

        return response()->json(['data' => $a->fresh()]);
    }

    public function destroyAccount(int $id): JsonResponse
    {
        $a = CrmAccount::query()->findOrFail($id);
        $a->delete();

        return response()->json([], 204);
    }

    public function account360(int $id): JsonResponse
    {
        $account = CrmAccount::query()->findOrFail($id);
        $contacts = CrmLead::query()
            ->where('converted_to_account_id', $id)
            ->orderByDesc('id')
            ->limit(100)
            ->get();
        $deals = Contract::query()
            ->where('customer_account_id', $id)
            ->orderByDesc('id')
            ->limit(100)
            ->get();
        $activities = \Modules\Crm\Entities\CrmActivity::query()
            ->where('related_model', CrmAccount::class)
            ->where('related_id', $id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => [
                'account' => $account,
                'contacts' => $contacts,
                'deals' => $deals,
                'activities' => $activities,
            ],
        ]);
    }

    public function exportAccounts(): StreamedResponse
    {
        $filename = 'accounts-'.now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'name', 'type', 'account_code', 'website', 'owner_id', 'created_at']);

            CrmAccount::query()->orderBy('id')->chunk(500, function ($chunk) use ($out) {
                foreach ($chunk as $acc) {
                    fputcsv($out, [
                        $acc->id,
                        $acc->name,
                        $acc->type,
                        $acc->account_code,
                        $acc->website,
                        $acc->owner_id,
                        optional($acc->created_at)->toIso8601String(),
                    ]);
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function importAccounts(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:5120']);
        $path = $request->file('file')->getRealPath();
        $imported = 0;
        if ($path && ($h = fopen($path, 'r')) !== false) {
            fgetcsv($h);
            while (($row = fgetcsv($h)) !== false) {
                if (count($row) < 2) {
                    continue;
                }
                CrmAccount::query()->create([
                    'name' => $row[1] ?? 'imported',
                    'type' => $row[2] ?? 'customer',
                    'account_code' => $row[3] ?? null,
                    'website' => $row[4] ?? null,
                    'owner_id' => is_numeric($row[5] ?? null) ? (int) $row[5] : null,
                    'created_by' => auth()->id(),
                ]);
                $imported++;
            }
            fclose($h);
        }

        return response()->json(['data' => ['imported' => $imported]]);
    }

    public function accountsList(): JsonResponse
    {
        return response()->json([
            'data' => CrmAccount::query()->orderBy('name')->limit(500)->get(['id', 'name', 'type']),
        ]);
    }

    public function consultationsIndex(): JsonResponse
    {
        return response()->json(['data' => CrmConsultation::query()->with('account')->orderByDesc('id')->get()]);
    }

    public function manageConsultation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => 'nullable|exists:crm_consultations,id',
            'title' => 'required|string|max:255',
            'account_id' => 'nullable|exists:crm_accounts,id',
            'status' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ]);
        if (! empty($data['id'])) {
            $row = CrmConsultation::query()->findOrFail($data['id']);
            $row->update(collect($data)->except('id')->all());
        } else {
            $row = CrmConsultation::query()->create(array_merge($data, ['created_by' => auth()->id()]));
        }

        return response()->json(['data' => $row], empty($data['id']) ? 201 : 200);
    }

    public function convertConsultation(int $id): JsonResponse
    {
        $c = CrmConsultation::query()->findOrFail($id);
        $project = Project::query()->create([
            'name' => 'From consultation: '.$c->title,
            'description' => $c->notes,
            'status' => 'active',
            'customer_account_id' => $c->account_id,
            'created_by' => auth()->id(),
        ]);

        return response()->json(['data' => ['project_id' => $project->id, 'project' => $project]]);
    }
}
