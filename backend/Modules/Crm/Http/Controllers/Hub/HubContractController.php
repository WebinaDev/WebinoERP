<?php

namespace Modules\Crm\Http\Controllers\Hub;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Crm\Entities\CrmAccount;
use Modules\Crm\Events\Customer\ContractSigned;
use Modules\Crm\Http\Requests\Hub\ContractCancelRequest;
use Modules\Crm\Http\Requests\Hub\ContractProjectRequest;
use Modules\Crm\Http\Requests\Hub\ContractRenewRequest;
use Modules\Crm\Http\Requests\Hub\ContractRequest;
use Modules\Crm\Http\Requests\Hub\ContractSignRequest;
use Modules\Crm\Http\Resources\Hub\ContractResource;

/** Customer 360 — Contracts tab (contract §5.2). Writes go straight to prj_contracts (shared with the Projects module). */
class HubContractController extends HubTabController
{
    private const T = 'prj_contracts';

    /** Allowed manual status changes via PATCH (sign / cancel have their own endpoints). */
    private const TRANSITIONS = [
        'draft' => ['active'],
        'active' => ['expired'],
        'signed' => ['active', 'expired'],
        'expired' => ['active'],
        'cancelled' => [],
    ];

    public function index(Request $r, int $id): JsonResponse
    {
        $this->gate($r, 'contracts');
        $a = $this->account($id);
        $r->validate([
            'status' => 'nullable|in:draft,active,signed,expired,cancelled', 'source' => 'nullable|in:projects,docs,all',
            'q' => 'nullable|string|max:100', 'expiring_in_days' => 'nullable|integer|min:0|max:3650',
            'page' => 'nullable|integer|min:1', 'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        $source = $r->query('source', 'all');
        $today = CarbonImmutable::today()->toDateString();
        $q = DB::table(self::T)->where('customer_account_id', $a->id);
        $hasEnd = $this->has(self::T, 'end_date');
        if ($s = $r->query('status')) {
            if (! $hasEnd) {
                $q->where('status', $s);
            } elseif ($s === 'expired') {
                $q->where(fn ($w) => $w->where('status', 'expired')->orWhere(fn ($w) => $w->whereIn('status', ['active', 'signed'])->where('end_date', '<', $today)));
            } elseif (in_array($s, ['active', 'signed'], true)) {
                $q->where('status', $s)->where(fn ($w) => $w->whereNull('end_date')->orWhere('end_date', '>=', $today));
            } else {
                $q->where('status', $s);
            }
        }
        if ($r->filled('expiring_in_days') && $hasEnd) {
            $q->whereIn('status', ['active', 'signed'])->whereBetween('end_date', [$today, CarbonImmutable::today()->addDays((int) $r->query('expiring_in_days'))->toDateString()]);
        }
        if ($r->filled('q')) {
            $like = $this->like((string) $r->query('q'));
            $q->where(fn ($w) => $w->where('title', 'like', $like)->when($this->has(self::T, 'contract_no'), fn ($w) => $w->orWhere('contract_no', 'like', $like)));
        }
        $kpi = $this->kpis($r, $a);
        $per = $this->perPage($r);
        $page = max(1, (int) $r->query('page', 1));

        if ($source === 'projects' || ! $this->has('docs_contracts', 'crm_account_id')) {
            $p = $this->sort($q, $r, array_values(array_filter(['created_at', 'amount', 'end_date', 'title'], fn ($c) => $this->has(self::T, $c))), '-created_at')
                ->paginate($per, ['*'], 'page', $page);
            $rows = $this->present($r, collect($p->items()));
            $p = new LengthAwarePaginator($source === 'docs' ? [] : $rows, $source === 'docs' ? 0 : $p->total(), $per, $page);
        } else {
            $rows = $source === 'docs' ? collect() : $this->present($r, $q->orderByDesc('id')->limit(1000)->get());
            $docs = DB::table('docs_contracts')->where('crm_account_id', $a->id)
                ->when($r->filled('status'), fn ($w) => $w->where('status', $r->query('status')))
                ->when($r->filled('q'), fn ($w) => $w->where('title', 'like', $this->like((string) $r->query('q'))))
                ->when($r->filled('expiring_in_days'), fn ($w) => $w->whereRaw('1 = 0'))
                ->orderByDesc('id')->limit(500)->get()->map(fn ($c) => ContractResource::fromDocs($c));
            $all = $rows->concat($docs)->sortByDesc(fn ($c) => (string) $c['created_at'])->values();
            $p = new LengthAwarePaginator($all->forPage($page, $per)->values(), $all->count(), $per, $page);
        }

        return response()->json(['data' => $p->items(), 'meta' => $this->meta($p, ['kpis' => $kpi, 'can' => ['create' => $this->canManage($r, 'contracts')]])]);
    }

    public function show(Request $r, int $id, int $contractId): JsonResponse
    {
        $this->gate($r, 'contracts');
        $a = $this->account($id);
        $c = $this->find($a, $contractId);
        $data = $this->present($r, collect([$c]))->first();
        $data['invoices'] = $this->seesMoney($r) ? DB::table('prj_pro_invoices')->where('contract_id', $c->id)->orderByDesc('id')->get(['id', 'number', 'status', 'total', 'created_at'])
            ->map(fn ($i) => ['id' => (int) $i->id, 'number' => $i->number, 'status' => $i->status, 'total' => (float) $i->total, 'created_at' => $i->created_at])->values() : [];
        $data['documents'] = $this->has('crm_customer_documents', 'contract_id')
            ? DB::table('crm_customer_documents')->where('account_id', $a->id)->where('contract_id', $c->id)->orderByDesc('id')->limit(50)->get(['id', 'title', 'category', 'file_name', 'created_at'])
                ->map(fn ($d) => ['id' => (int) $d->id, 'title' => $d->title, 'category' => $d->category, 'file_name' => $d->file_name, 'created_at' => $d->created_at])->values()
            : [];

        return response()->json(['data' => $data]);
    }

    public function store(ContractRequest $r, int $id): JsonResponse
    {
        $this->gateManage($r, 'contracts');
        $a = $this->account($id);
        $d = $r->validated();
        $cid = DB::transaction(function () use ($r, $a, $d) {
            $row = $this->fields($d) + [
                'customer_account_id' => $a->id, 'status' => 'draft', 'created_by' => $r->user()->id,
                'owner_id' => $d['owner_id'] ?? $r->user()->id, 'created_at' => now(), 'updated_at' => now(),
            ];
            $row['status'] = in_array($d['status'] ?? 'draft', ['draft', 'active'], true) ? ($d['status'] ?? 'draft') : 'draft';
            $cid = DB::table(self::T)->insertGetId($this->onlyColumns(self::T, $row));
            $this->syncInstallments($cid, $d['installments'] ?? []);
            $this->linkProjects($a, $cid, $d['project_ids'] ?? []);

            return $cid;
        });
        $this->audit($r, $a, 'contract.create', ['contract_id' => $cid, 'title' => $d['title'], 'amount' => $d['amount']]);

        return $this->show($r, $a->id, $cid)->setStatusCode(201);
    }

    public function update(ContractRequest $r, int $id, int $contractId): JsonResponse
    {
        $this->gateManage($r, 'contracts');
        $a = $this->account($id);
        $c = $this->find($a, $contractId);
        abort_if($c->status === 'cancelled', 422, __('A cancelled contract cannot be edited.'));
        $d = $r->validated();
        if (isset($d['status']) && $d['status'] !== $c->status && ! in_array($d['status'], self::TRANSITIONS[$c->status] ?? [], true)) {
            return response()->json(['message' => __('Invalid status transition.'), 'errors' => ['status' => [__('Cannot change status from :from to :to.', ['from' => $c->status, 'to' => $d['status']])]]], 422);
        }
        $end = $d['end_date'] ?? $c->end_date ?? null;
        $start = $d['start_date'] ?? $c->start_date ?? null;
        if ($start && $end && $end < $start) {
            return response()->json(['message' => __('The given data was invalid.'), 'errors' => ['end_date' => [__('validation.after_or_equal', ['attribute' => 'end_date', 'date' => 'start_date'])]]], 422);
        }
        DB::transaction(function () use ($a, $c, $d) {
            $row = $this->fields($d);
            if (isset($d['status'])) {
                $row['status'] = $d['status'];
            }
            DB::table(self::T)->where('id', $c->id)->update($this->onlyColumns(self::T, $row + ['updated_at' => now()]));
            if (array_key_exists('installments', $d)) {
                $this->syncInstallments($c->id, $d['installments'] ?? []);
            }
            if (array_key_exists('project_ids', $d)) {
                $this->linkProjects($a, $c->id, $d['project_ids'] ?? [], true);
            }
        });
        $this->audit($r, $a, 'contract.update', ['contract_id' => $c->id, 'fields' => array_keys($d)]);

        return $this->show($r, $a->id, $c->id);
    }

    public function sign(ContractSignRequest $r, int $id, int $contractId): JsonResponse
    {
        $this->gateManage($r, 'contracts');
        $a = $this->account($id);
        $c = $this->find($a, $contractId);
        abort_unless(in_array(ContractResource::effectiveStatus($c), ['draft', 'active'], true), 422, __('Only draft or active contracts can be signed.'));
        $docId = null;
        DB::transaction(function () use ($r, $a, $c, &$docId) {
            if ($r->hasFile('file')) {
                $doc = $this->onlyColumns('crm_customer_documents', [
                    'account_id' => $a->id, 'category' => 'contract', 'title' => __('Signed contract').': '.$c->title,
                    'review_status' => 'approved', 'reviewed_by' => $r->user()->id, 'reviewed_at' => now(), 'version' => 1,
                    'contract_id' => $c->id, 'created_by' => $r->user()->id, 'created_at' => now(), 'updated_at' => now(),
                ] + $this->storeFile($a, $r->file('file')));
                $docId = DB::table('crm_customer_documents')->insertGetId($doc);
            }
            DB::table(self::T)->where('id', $c->id)->update($this->onlyColumns(self::T, [
                'status' => 'signed', 'signed_at' => $r->input('signed_at') ? CarbonImmutable::parse($r->input('signed_at')) : now(),
                'signed_document_id' => $docId ?? ($c->signed_document_id ?? null), 'updated_at' => now(),
            ]));
        });
        $this->audit($r, $a, 'contract.sign', ['contract_id' => $c->id, 'document_id' => $docId]);
        event(new ContractSigned($a->id, $c->id, $r->user()->id, $docId));

        return $this->show($r, $a->id, $c->id);
    }

    public function cancel(ContractCancelRequest $r, int $id, int $contractId): JsonResponse
    {
        $this->gateManage($r, 'contracts');
        $a = $this->account($id);
        $c = $this->find($a, $contractId);
        abort_if($c->status === 'cancelled', 422, __('Contract is already cancelled.'));
        $reason = (string) $r->validated()['reason'];
        $notes = trim(($c->notes ? $c->notes."\n\n" : '').'['.now()->toDateString().'] '.__('Cancelled').': '.$reason);
        DB::table(self::T)->where('id', $c->id)->update(['status' => 'cancelled', 'notes' => $notes, 'updated_at' => now()]);
        $this->audit($r, $a, 'contract.cancel', ['contract_id' => $c->id, 'reason' => $reason]);

        return $this->show($r, $a->id, $c->id);
    }

    /** Renewal: creates a new draft contract continuing this one (start = old end + 1 day, same duration). */
    public function renew(ContractRenewRequest $r, int $id, int $contractId): JsonResponse
    {
        $this->gateManage($r, 'contracts');
        $a = $this->account($id);
        $c = $this->find($a, $contractId);
        abort_unless(in_array(ContractResource::effectiveStatus($c), ['active', 'signed', 'expired'], true), 422, __('Only active, signed or expired contracts can be renewed.'));
        $d = $r->validated();
        $oldStart = ! empty($c->start_date) ? CarbonImmutable::parse($c->start_date) : null;
        $oldEnd = ! empty($c->end_date) ? CarbonImmutable::parse($c->end_date) : null;
        $start = isset($d['start_date']) ? CarbonImmutable::parse($d['start_date']) : ($oldEnd?->addDay() ?? CarbonImmutable::today());
        $end = isset($d['end_date']) ? CarbonImmutable::parse($d['end_date']) : ($oldStart && $oldEnd ? $start->addDays($oldStart->diffInDays($oldEnd)) : null);
        if ($end && $end->lt($start)) {
            return response()->json(['message' => __('The given data was invalid.'), 'errors' => ['end_date' => [__('validation.after_or_equal', ['attribute' => 'end_date', 'date' => 'start_date'])]]], 422);
        }
        $row = (array) $c;
        foreach (['id', 'signed_at', 'signed_document_id', 'contract_no', 'installments_data'] as $k) {
            unset($row[$k]);
        }
        $newId = DB::table(self::T)->insertGetId($this->onlyColumns(self::T, array_merge($row, [
            'title' => $c->title, 'status' => 'draft', 'amount' => $d['amount'] ?? $c->amount,
            'start_date' => $start->toDateString(), 'end_date' => $end?->toDateString(), 'renewed_from_id' => $c->id,
            'created_by' => $r->user()->id, 'created_at' => now(), 'updated_at' => now(),
        ])));
        $this->audit($r, $a, 'contract.renew', ['contract_id' => $newId, 'renewed_from_id' => $c->id]);

        return $this->show($r, $a->id, $newId)->setStatusCode(201);
    }

    /** Create a pro-forma invoice for one installment and link it. Idempotent. */
    public function invoiceInstallment(Request $r, int $id, int $contractId, int $instId): JsonResponse
    {
        $this->gateManage($r, 'contracts');
        abort_unless($this->seesMoney($r), 403, __('Forbidden'));
        $a = $this->account($id);
        $c = $this->find($a, $contractId);
        abort_if($c->status === 'cancelled', 422, __('A cancelled contract cannot be invoiced.'));
        abort_unless($this->has('prj_contract_installments', 'invoice_id'), 422, __('Installment invoicing is not available yet.'));
        $inst = DB::table('prj_contract_installments')->where('contract_id', $c->id)->where('id', $instId)->first();
        abort_unless($inst, 404);
        if ($inst->invoice_id) {
            return response()->json(['data' => ['invoice_id' => (int) $inst->invoice_id, 'created' => false]]);
        }
        $invId = DB::transaction(function () use ($r, $c, $inst) {
            $title = $inst->title ?: __('Installment').' — '.$c->title;
            $invId = DB::table('prj_pro_invoices')->insertGetId($this->onlyColumns('prj_pro_invoices', [
                'number' => 'C'.$c->id.'-'.$inst->id, 'contract_id' => $c->id, 'project_id' => $c->project_id ?? null,
                'status' => 'draft', 'total' => $inst->amount, 'discount' => 0,
                'items' => json_encode([['title' => $title, 'qty' => 1, 'price' => (float) $inst->amount, 'installment_id' => (int) $inst->id]], JSON_UNESCAPED_UNICODE),
                'customer_user_id' => $c->customer_user_id ?? null, 'created_by' => $r->user()->id, 'created_at' => now(), 'updated_at' => now(),
            ]));
            DB::table('prj_contract_installments')->where('id', $inst->id)->update(['invoice_id' => $invId, 'updated_at' => now()]);

            return $invId;
        });
        $this->audit($r, $a, 'contract.installment_invoice', ['contract_id' => $c->id, 'installment_id' => $inst->id, 'invoice_id' => $invId]);

        return response()->json(['data' => ['invoice_id' => (int) $invId, 'created' => true]], 201);
    }

    public function attachProject(ContractProjectRequest $r, int $id, int $contractId): JsonResponse
    {
        $this->gateManage($r, 'contracts');
        $a = $this->account($id);
        $c = $this->find($a, $contractId);
        $pid = (int) $r->validated()['project_id'];
        $this->linkProjects($a, $c->id, [$pid]);
        $this->audit($r, $a, 'contract.attach_project', ['contract_id' => $c->id, 'project_id' => $pid]);

        return $this->show($r, $a->id, $c->id);
    }

    public function destroy(Request $r, int $id, int $contractId): Response|JsonResponse
    {
        $this->gateManage($r, 'contracts');
        $a = $this->account($id);
        $c = $this->find($a, $contractId);
        if ($c->status !== 'draft') {
            return response()->json(['message' => __('Only draft contracts can be deleted.'), 'errors' => ['status' => [__('Only draft contracts can be deleted.')]]], 422);
        }
        DB::transaction(function () use ($c) {
            DB::table('prj_contract_installments')->where('contract_id', $c->id)->delete();
            if ($this->has('prj_projects', 'contract_id')) {
                DB::table('prj_projects')->where('contract_id', $c->id)->update(['contract_id' => null]);
            }
            DB::table(self::T)->where('id', $c->id)->delete();
        });
        $this->audit($r, $a, 'contract.delete', ['contract_id' => $c->id, 'title' => $c->title]);

        return response()->noContent();
    }

    // ---------------------------------------------------------------------

    private function find(CrmAccount $a, int $contractId): object
    {
        $c = DB::table(self::T)->where('customer_account_id', $a->id)->where('id', $contractId)->first();
        abort_unless($c, 404);

        return $c;
    }

    private function fields(array $d): array
    {
        $keys = ['title', 'amount', 'contract_no', 'start_date', 'end_date', 'renewal_type', 'renewal_notice_days', 'currency', 'tax_pct', 'deal_id', 'owner_id', 'notes'];
        $row = array_intersect_key($d, array_flip($keys));
        if (isset($row['currency'])) {
            $row['currency'] = strtoupper($row['currency']);
        }

        return $row;
    }

    /** Replace installments; paid or invoiced ones are kept untouched. */
    private function syncInstallments(int $cid, array $items): void
    {
        $T = 'prj_contract_installments';
        $locked = fn ($i) => ! empty($i->paid_at) || ! empty($i->invoice_id ?? null);
        $existing = DB::table($T)->where('contract_id', $cid)->get()->keyBy('id');
        $keep = [];
        foreach ($items as $it) {
            $row = $this->onlyColumns($T, ['due_date' => $it['due_date'], 'amount' => $it['amount'], 'title' => $it['title'] ?? null, 'updated_at' => now()]);
            $old = isset($it['id']) ? $existing->get((int) $it['id']) : null;
            if ($old) {
                $keep[] = $old->id;
                if (! $locked($old)) {
                    DB::table($T)->where('id', $old->id)->update($row);
                }
            } else {
                $keep[] = DB::table($T)->insertGetId($row + ['contract_id' => $cid, 'created_at' => now()]);
            }
        }
        $drop = $existing->reject(fn ($i) => in_array($i->id, $keep) || $locked($i))->keys();
        if ($drop->isNotEmpty()) {
            DB::table($T)->whereIn('id', $drop)->delete();
        }
    }

    /** project ids are validated as this customer's already. */
    private function linkProjects(CrmAccount $a, int $cid, array $pids, bool $replace = false): void
    {
        $pids = array_values(array_unique(array_map('intval', $pids)));
        if ($this->has('prj_projects', 'contract_id')) {
            if ($replace) {
                DB::table('prj_projects')->where('customer_account_id', $a->id)->where('contract_id', $cid)->whereNotIn('id', $pids ?: [0])->update(['contract_id' => null]);
            }
            if ($pids) {
                DB::table('prj_projects')->where('customer_account_id', $a->id)->whereIn('id', $pids)->update(['contract_id' => $cid, 'updated_at' => now()]);
            }
        }
        // legacy single link used by the Projects module
        $c = DB::table(self::T)->where('id', $cid)->first(['project_id']);
        if ($pids && (! $c->project_id || ($replace && ! in_array((int) $c->project_id, $pids, true)))) {
            DB::table(self::T)->where('id', $cid)->update(['project_id' => $pids[0]]);
        } elseif ($replace && ! $pids) {
            DB::table(self::T)->where('id', $cid)->update(['project_id' => null]);
        }
    }

    private function kpis(Request $r, CrmAccount $a): array
    {
        $rows = DB::table(self::T)->where('customer_account_id', $a->id)->get();
        $eff = $rows->map(fn ($c) => ['s' => ContractResource::effectiveStatus($c), 'amount' => (float) $c->amount, 'end' => $c->end_date ?? null]);
        $live = $eff->whereIn('s', ['active', 'signed']);
        $soon = CarbonImmutable::today()->addDays(30)->toDateString();
        $money = $this->seesMoney($r);
        $paid = $money ? (float) DB::table('prj_contract_installments')->whereIn('contract_id', $rows->pluck('id'))->whereNotNull('paid_at')->sum('amount') : null;

        return [
            'total' => $rows->count(), 'active' => $live->count(), 'draft' => $eff->where('s', 'draft')->count(),
            'expiring_30' => $live->filter(fn ($c) => $c['end'] && $c['end'] <= $soon)->count(),
            'active_amount' => $money ? $live->sum('amount') : null, 'paid_total' => $paid,
        ];
    }

    /** Batch-present contract rows (installments, projects, refs). */
    private function present(Request $r, Collection $rows): Collection
    {
        if ($rows->isEmpty()) {
            return collect();
        }
        $ids = $rows->pluck('id');
        $inst = DB::table('prj_contract_installments')->whereIn('contract_id', $ids)->orderBy('due_date')->orderBy('id')->get()->groupBy('contract_id');
        $inv = DB::table('prj_pro_invoices')->whereIn('contract_id', $ids)->where('status', '!=', 'cancelled')->selectRaw('contract_id, SUM(total) as t')->groupBy('contract_id')->pluck('t', 'contract_id');
        $projQ = DB::table('prj_projects')->where(fn ($w) => $w->whereIn('id', $rows->pluck('project_id')->filter())
            ->when($this->has('prj_projects', 'contract_id'), fn ($w) => $w->orWhereIn('contract_id', $ids)));
        $projects = $projQ->get(array_values(array_filter(['id', 'name', 'status', 'contract_id'], fn ($c) => $this->has('prj_projects', $c))));
        $users = DB::table('users')->whereIn('id', $rows->map(fn ($c) => $c->owner_id ?? $c->created_by)->filter()->unique())->pluck('name', 'id');
        $deals = $this->has(self::T, 'deal_id') && $rows->pluck('deal_id')->filter()->isNotEmpty() ? DB::table('crm_deals')->whereIn('id', $rows->pluck('deal_id')->filter())->pluck('name', 'id') : collect();
        $docs = $this->has(self::T, 'signed_document_id') && $rows->pluck('signed_document_id')->filter()->isNotEmpty()
            ? DB::table('crm_customer_documents')->whereIn('id', $rows->pluck('signed_document_id')->filter())->get(['id', 'title', 'file_name'])->keyBy('id') : collect();
        $money = $this->seesMoney($r);
        $manage = $this->canManage($r, 'contracts');

        return $rows->map(function ($c) use ($inst, $inv, $projects, $users, $deals, $docs, $money, $manage) {
            $ownerId = $c->owner_id ?? $c->created_by;
            $doc = ! empty($c->signed_document_id) ? $docs->get($c->signed_document_id) : null;

            return ContractResource::fromRow($c, [
                'money' => $money, 'manage' => $manage, 'installments' => $inst->get($c->id, collect()),
                'invoiced_total' => (float) ($inv[$c->id] ?? 0),
                'projects' => $projects->filter(fn ($p) => (int) $p->id === (int) $c->project_id || (int) ($p->contract_id ?? 0) === (int) $c->id)
                    ->map(fn ($p) => ['id' => (int) $p->id, 'name' => $p->name, 'status' => $p->status, 'url' => '/projects/'.$p->id])->values()->all(),
                'deal' => ! empty($c->deal_id) ? ['id' => (int) $c->deal_id, 'name' => $deals[$c->deal_id] ?? null] : null,
                'signed_document' => $doc ? ['id' => (int) $doc->id, 'title' => $doc->title, 'file_name' => $doc->file_name] : null,
                'owner' => $ownerId ? ['id' => (int) $ownerId, 'name' => $users[$ownerId] ?? null] : null,
            ]);
        })->values();
    }
}
