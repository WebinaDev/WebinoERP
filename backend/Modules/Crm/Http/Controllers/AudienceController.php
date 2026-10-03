<?php

namespace Modules\Crm\Http\Controllers;

use App\Support\CalendarDate;
use App\Support\MutationAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Crm\Entities\CrmAccount;
use Modules\Crm\Entities\CrmContact;
use Modules\Crm\Entities\CrmCustomField;
use Modules\Crm\Entities\CrmCustomFieldValue;
use Modules\Crm\Entities\CrmLead;
use Modules\Crm\Entities\CrmListMember;
use Modules\Crm\Entities\CrmMarketingList;
use Modules\Crm\Entities\CrmSegment;
use Modules\Crm\Entities\CrmTag;
use Modules\Crm\Services\CrmMergeService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AudienceController extends Controller
{
    public function tags(): JsonResponse
    {
        return response()->json(['data' => CrmTag::query()->orderBy('name')->get()]);
    }

    public function storeTag(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:80|unique:crm_tags,name',
            'color' => 'nullable|string|max:7',
        ]);
        $tag = CrmTag::query()->create([
            'name' => $data['name'],
            'color' => $data['color'] ?? '#64748b',
        ]);

        return response()->json(['data' => $tag], 201);
    }

    public function attachTag(Request $request, int $id): JsonResponse
    {
        $tag = CrmTag::query()->findOrFail($id);
        $data = $request->validate([
            'taggable_type' => 'required|string|in:account,contact,lead,deal',
            'taggable_id' => 'required|integer|min:1',
        ]);
        DB::table('crm_taggables')->updateOrInsert([
            'tag_id' => $tag->id,
            'taggable_type' => $data['taggable_type'],
            'taggable_id' => $data['taggable_id'],
        ], ['created_at' => now(), 'updated_at' => now()]);

        return response()->json(['data' => ['attached' => true]]);
    }

    public function detachTag(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'taggable_type' => 'required|string|in:account,contact,lead,deal',
            'taggable_id' => 'required|integer|min:1',
        ]);
        DB::table('crm_taggables')
            ->where('tag_id', $id)
            ->where('taggable_type', $data['taggable_type'])
            ->where('taggable_id', $data['taggable_id'])
            ->delete();

        return response()->json([], 204);
    }

    public function tagged(Request $request): JsonResponse
    {
        $data = $request->validate([
            'taggable_type' => 'required|string|in:account,contact,lead,deal',
            'taggable_id' => 'required|integer|min:1',
        ]);
        $ids = DB::table('crm_taggables')
            ->where('taggable_type', $data['taggable_type'])
            ->where('taggable_id', $data['taggable_id'])
            ->pluck('tag_id');

        return response()->json(['data' => CrmTag::query()->whereIn('id', $ids)->get()]);
    }

    public function segments(): JsonResponse
    {
        return response()->json(['data' => CrmSegment::query()->orderBy('name')->get()]);
    }

    public function storeSegment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:160',
            'entity' => 'required|string|in:account,lead',
            'filters' => 'nullable|array',
        ]);
        $data['created_by'] = $request->user()->id;
        $row = CrmSegment::query()->create($data);

        return response()->json(['data' => $row], 201);
    }

    public function previewSegment(int $id): JsonResponse
    {
        $segment = CrmSegment::query()->findOrFail($id);
        $rows = $this->segmentQuery($segment)->limit(25)->get();

        return response()->json([
            'data' => [
                'count' => $this->segmentQuery($segment)->count(),
                'sample' => $rows,
            ],
        ]);
    }

    public function lists(): JsonResponse
    {
        return response()->json([
            'data' => CrmMarketingList::query()->withCount('members')->orderBy('name')->get(),
        ]);
    }

    public function storeList(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:160',
            'description' => 'nullable|string',
            'campaign_id' => 'nullable|integer',
        ]);
        if (! empty($data['campaign_id']) && DB::getSchemaBuilder()->hasTable('sales_campaigns')) {
            abort_unless(DB::table('sales_campaigns')->where('id', $data['campaign_id'])->exists(), 422, 'Campaign not found');
        }
        $data['created_by'] = $request->user()->id;
        $row = CrmMarketingList::query()->create($data);
        MutationAudit::record($request->user()->id, 'crm', 'list.create', CrmMarketingList::class, $row->id, [
            'campaign_id' => $row->campaign_id,
        ]);

        return response()->json(['data' => $row], 201);
    }

    public function updateList(Request $request, int $id): JsonResponse
    {
        $row = CrmMarketingList::query()->findOrFail($id);
        $data = $request->validate([
            'name' => 'sometimes|string|max:160',
            'description' => 'nullable|string',
            'campaign_id' => 'nullable|integer',
        ]);
        if (array_key_exists('campaign_id', $data) && $data['campaign_id'] && DB::getSchemaBuilder()->hasTable('sales_campaigns')) {
            abort_unless(DB::table('sales_campaigns')->where('id', $data['campaign_id'])->exists(), 422, 'Campaign not found');
        }
        $row->update($data);

        return response()->json(['data' => $row->fresh()]);
    }

    public function addMember(Request $request, int $id): JsonResponse
    {
        CrmMarketingList::query()->findOrFail($id);
        $data = $request->validate([
            'member_type' => 'required|string|in:account,contact,lead',
            'member_id' => 'required|integer|min:1',
        ]);
        $member = CrmListMember::query()->firstOrCreate([
            'list_id' => $id,
            'member_type' => $data['member_type'],
            'member_id' => $data['member_id'],
        ]);

        return response()->json(['data' => $member], 201);
    }

    public function removeMember(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'member_type' => 'required|string|in:account,contact,lead',
            'member_id' => 'required|integer|min:1',
        ]);
        CrmListMember::query()
            ->where('list_id', $id)
            ->where('member_type', $data['member_type'])
            ->where('member_id', $data['member_id'])
            ->delete();

        return response()->json([], 204);
    }

    public function fields(Request $request): JsonResponse
    {
        $query = CrmCustomField::query()->orderBy('sort_order');
        if ($request->filled('entity')) {
            $query->where('entity', $request->string('entity'));
        }

        return response()->json(['data' => $query->get()]);
    }

    public function storeField(Request $request): JsonResponse
    {
        $data = $request->validate([
            'entity' => 'required|string|in:account,contact,deal,lead',
            'key' => 'required|string|max:64|regex:/^[a-z0-9_]+$/',
            'label' => 'required|string|max:120',
            'type' => 'required|string|in:text,number,date,select',
            'options' => 'nullable|array',
            'is_required' => 'nullable|boolean',
        ]);
        $row = CrmCustomField::query()->create($data);
        MutationAudit::record($request->user()->id, 'crm', 'field.create', CrmCustomField::class, $row->id, ['key' => $row->key]);

        return response()->json(['data' => $row], 201);
    }

    public function destroyField(int $id): JsonResponse
    {
        CrmCustomField::query()->findOrFail($id)->delete();

        return response()->json([], 204);
    }

    public function values(Request $request): JsonResponse
    {
        $data = $request->validate([
            'entity_type' => 'required|string|in:account,contact,deal,lead',
            'entity_id' => 'required|integer|min:1',
        ]);
        $fields = CrmCustomField::query()->where('entity', $data['entity_type'])->orderBy('sort_order')->get();
        $stored = CrmCustomFieldValue::query()
            ->where('entity_type', $data['entity_type'])
            ->where('entity_id', $data['entity_id'])
            ->get()
            ->keyBy('field_id');

        $rows = $fields->map(fn (CrmCustomField $field) => [
            'field' => $field,
            'value' => $stored[$field->id]->value ?? null,
        ]);

        return response()->json(['data' => $rows]);
    }

    public function saveValues(Request $request): JsonResponse
    {
        $data = $request->validate([
            'entity_type' => 'required|string|in:account,contact,deal,lead',
            'entity_id' => 'required|integer|min:1',
            'values' => 'required|array',
        ]);
        foreach ($data['values'] as $key => $value) {
            $field = CrmCustomField::query()->where('entity', $data['entity_type'])->where('key', $key)->first();
            if (! $field) {
                continue;
            }
            CrmCustomFieldValue::query()->updateOrCreate(
                [
                    'field_id' => $field->id,
                    'entity_type' => $data['entity_type'],
                    'entity_id' => $data['entity_id'],
                ],
                ['value' => $value === null ? null : (string) $value],
            );
        }
        MutationAudit::record($request->user()->id, 'crm', 'field.values', $data['entity_type'], (int) $data['entity_id'], [
            'keys' => array_keys($data['values']),
        ]);

        return response()->json(['data' => ['saved' => true]]);
    }

    public function accountDuplicates(int $id): JsonResponse
    {
        $account = CrmAccount::query()->findOrFail($id);
        $query = CrmAccount::query()->where('id', '!=', $account->id)->where(function ($w) use ($account) {
            $w->whereRaw('lower(name) = ?', [mb_strtolower($account->name)]);
            if ($account->website) {
                $w->orWhere('website', $account->website);
            }
        });

        return response()->json(['data' => $query->limit(20)->get()]);
    }

    public function contactDuplicates(int $id): JsonResponse
    {
        $contact = CrmContact::query()->findOrFail($id);
        $query = CrmContact::query()->where('id', '!=', $contact->id)->where(function ($w) use ($contact) {
            if ($contact->email) {
                $w->orWhere('email', $contact->email);
            }
            if ($contact->mobile) {
                $w->orWhere('mobile', $contact->mobile);
            }
        });

        return response()->json(['data' => $query->limit(20)->get()]);
    }

    public function mergeAccounts(Request $request, CrmMergeService $merge): JsonResponse
    {
        $data = $request->validate([
            'primary_id' => 'required|integer|exists:crm_accounts,id',
            'duplicate_id' => 'required|integer|exists:crm_accounts,id',
        ]);
        $account = $merge->mergeAccounts((int) $data['primary_id'], (int) $data['duplicate_id'], $request->user()->id);

        return response()->json(['data' => $account]);
    }

    public function mergeContacts(Request $request, CrmMergeService $merge): JsonResponse
    {
        $data = $request->validate([
            'primary_id' => 'required|integer|exists:crm_contacts,id',
            'duplicate_id' => 'required|integer|exists:crm_contacts,id',
        ]);
        $contact = $merge->mergeContacts((int) $data['primary_id'], (int) $data['duplicate_id'], $request->user()->id);

        return response()->json(['data' => $contact]);
    }

    public function exportContacts(): StreamedResponse
    {
        $filename = 'contacts-'.now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            $locale = CalendarDate::localeFromRequest();
            fputcsv($out, ['id', 'account_id', 'first_name', 'last_name', 'email', 'mobile', 'phone', 'job_title', 'created_at_display']);
            CrmContact::query()->orderBy('id')->chunk(500, function ($chunk) use ($out, $locale) {
                foreach ($chunk as $contact) {
                    fputcsv($out, [
                        $contact->id,
                        $contact->account_id,
                        $contact->first_name,
                        $contact->last_name,
                        $contact->email,
                        $contact->mobile,
                        $contact->phone,
                        $contact->job_title,
                        CalendarDate::format($contact->created_at, $locale, true),
                    ]);
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function importContacts(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:5120']);
        $path = $request->file('file')->getRealPath();
        $imported = 0;
        if ($path && ($handle = fopen($path, 'r')) !== false) {
            fgetcsv($handle);
            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) < 4 || ! is_numeric($row[1] ?? null)) {
                    continue;
                }
                CrmContact::query()->create([
                    'account_id' => (int) $row[1],
                    'first_name' => $row[2] ?: 'imported',
                    'last_name' => $row[3] ?: '-',
                    'email' => $row[4] ?? null,
                    'mobile' => $row[5] ?? null,
                    'phone' => $row[6] ?? null,
                    'job_title' => $row[7] ?? null,
                    'created_by' => $request->user()->id,
                ]);
                $imported++;
            }
            fclose($handle);
        }
        MutationAudit::record($request->user()->id, 'crm', 'contact.import', CrmContact::class, null, ['imported' => $imported]);

        return response()->json(['data' => ['imported' => $imported]]);
    }

    public function audit(): JsonResponse
    {
        $rows = DB::table('ops_audit_logs')->where('module', 'crm')->orderByDesc('id')->limit(100)->get();

        return response()->json(['data' => $rows]);
    }

    private function segmentQuery(CrmSegment $segment)
    {
        $filters = $segment->filters ?? [];
        if ($segment->entity === 'lead') {
            $query = CrmLead::query();
            if (! empty($filters['source_id'])) {
                $query->where('source_id', (int) $filters['source_id']);
            }
            if (! empty($filters['assigned_to'])) {
                $query->where('assigned_to', (int) $filters['assigned_to']);
            }
            if (! empty($filters['min_score'])) {
                $query->where('lead_score', '>=', (int) $filters['min_score']);
            }

            return $query;
        }

        $query = CrmAccount::query();
        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (! empty($filters['industry'])) {
            $query->where('industry', $filters['industry']);
        }
        if (! empty($filters['owner_id'])) {
            $query->where('owner_id', (int) $filters['owner_id']);
        }
        if (! empty($filters['tag_id'])) {
            $ids = DB::table('crm_taggables')
                ->where('tag_id', (int) $filters['tag_id'])
                ->where('taggable_type', 'account')
                ->pluck('taggable_id');
            $query->whereIn('id', $ids);
        }

        return $query;
    }
}
