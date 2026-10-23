<?php

namespace Modules\Crm\Http\Controllers\Hub;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Crm\Entities\CrmAccount;
use Modules\Crm\Http\Requests\Hub\DocumentRequest;
use Modules\Crm\Http\Requests\Hub\DocumentVersionRequest;
use Modules\Crm\Http\Resources\Hub\DocumentResource;

/** Customer 360 — Documents tab (contract §5.1). */
class HubDocumentController extends HubTabController
{
    private const T = 'crm_customer_documents';

    public function index(Request $r, int $id): JsonResponse
    {
        $this->gate($r, 'documents');
        $a = $this->account($id);
        $r->validate([
            'category' => 'nullable|in:'.implode(',', DocumentRequest::CATEGORIES),
            'review_status' => 'nullable|in:pending,approved,rejected',
            'expired' => 'nullable|in:0,1,true,false',
            'source' => 'nullable|in:hub,projects,docs,all',
            'contract_id' => 'nullable|integer', 'project_id' => 'nullable|integer',
            'q' => 'nullable|string|max:100', 'page' => 'nullable|integer|min:1', 'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        $source = $r->query('source', 'all');
        $rows = collect();
        if (in_array($source, ['hub', 'all'], true)) {
            $rows = $rows->concat($this->hubRows($r, $a));
        }
        if (in_array($source, ['projects', 'all'], true) && ! $r->filled('contract_id') && ! $r->filled('review_status')) {
            $rows = $rows->concat($this->projectFileRows($r, $a));
        }
        if (in_array($source, ['docs', 'all'], true) && ! $r->filled('contract_id') && ! $r->filled('project_id') && ! $r->filled('review_status')) {
            $rows = $rows->concat($this->docsFileRows($r, $a));
        }
        if ($r->filled('category')) {
            $rows = $rows->where('category', $r->query('category'));
        }
        if ($r->filled('expired')) {
            $want = filter_var($r->query('expired'), FILTER_VALIDATE_BOOLEAN);
            $rows = $rows->filter(fn ($d) => $d['expired'] === $want);
        }
        $sort = (string) $r->query('sort', '-created_at');
        $key = in_array(ltrim($sort, '-'), ['created_at', 'title', 'expires_at'], true) ? ltrim($sort, '-') : 'created_at';
        $rows = $rows->sortBy(fn ($d) => (string) ($d[$key] ?? ''), SORT_NATURAL, str_starts_with($sort, '-'))->values();

        $per = $this->perPage($r);
        $page = max(1, (int) $r->query('page', 1));
        $p = new LengthAwarePaginator($rows->forPage($page, $per)->values(), $rows->count(), $per, $page);

        return response()->json(['data' => $p->items(), 'meta' => $this->meta($p, ['can' => ['create' => $this->canManage($r, 'documents')]])]);
    }

    public function show(Request $r, int $id, int $docId): JsonResponse
    {
        $this->gate($r, 'documents');
        $a = $this->account($id);
        $d = $this->find($r, $a, $docId);
        $refs = $this->refs(collect([$d]));
        $ctx = ['can_manage' => $this->canManage($r, 'documents'), 'refs' => $refs];
        $versions = $this->chain($d)->map(fn ($v) => DocumentResource::fromRow($v, $ctx))->values();

        return response()->json(['data' => DocumentResource::fromRow($d, $ctx) + ['versions' => $versions]]);
    }

    public function store(DocumentRequest $r, int $id): JsonResponse
    {
        $this->gateManage($r, 'documents');
        $a = $this->account($id);
        $data = $r->validated();
        $row = [
            'account_id' => $a->id, 'category' => $data['category'], 'title' => $data['title'],
            'url' => $data['url'] ?? null, 'expires_at' => $data['expires_at'] ?? null, 'review_status' => 'pending',
            'visible_to_roles' => isset($data['visible_to_roles']) ? json_encode(array_values($data['visible_to_roles'])) : null,
            'tags' => isset($data['tags']) ? json_encode(array_values($data['tags']), JSON_UNESCAPED_UNICODE) : null,
            'contract_id' => $data['contract_id'] ?? null, 'project_id' => $data['project_id'] ?? null,
            'version' => 1, 'created_by' => $r->user()->id, 'created_at' => now(), 'updated_at' => now(),
        ];
        if ($r->hasFile('file')) {
            $row += $this->storeFile($a, $r->file('file'));
        }
        $docId = DB::table(self::T)->insertGetId($this->onlyColumns(self::T, $row));
        $this->audit($r, $a, 'document.upload', ['document_id' => $docId, 'category' => $row['category'], 'title' => $row['title']]);

        return $this->show($r, $a->id, $docId)->setStatusCode(201);
    }

    public function update(DocumentRequest $r, int $id, int $docId): JsonResponse
    {
        $this->gateManage($r, 'documents');
        $a = $this->account($id);
        $d = $this->find($r, $a, $docId);
        $data = $r->validated();
        foreach (['visible_to_roles', 'tags'] as $k) {
            if (array_key_exists($k, $data)) {
                $data[$k] = $data[$k] !== null ? json_encode(array_values($data[$k]), JSON_UNESCAPED_UNICODE) : null;
            }
        }
        if (isset($data['review_status'])) {
            $data['reviewed_by'] = $r->user()->id;
            $data['reviewed_at'] = now();
        }
        DB::table(self::T)->where('id', $d->id)->update($this->onlyColumns(self::T, $data + ['updated_at' => now()]));
        if (isset($data['review_status'])) {
            $this->audit($r, $a, 'document.review', ['document_id' => $d->id, 'status' => $data['review_status']]);
            // same KYC rule as the core hub: approved national card / gazette marks the customer verified
            if (in_array($d->category, ['national_card', 'gazette'], true) && $this->has('crm_accounts', 'kyc_status')) {
                $kyc = $data['review_status'] === 'approved' ? 'verified' : ($data['review_status'] === 'rejected' ? 'rejected' : null);
                if ($kyc) {
                    DB::table('crm_accounts')->where('id', $a->id)->update(['kyc_status' => $kyc, 'updated_at' => now()]);
                }
            }
        } else {
            $this->audit($r, $a, 'document.update', ['document_id' => $d->id, 'fields' => array_keys($data)]);
        }

        return $this->show($r, $a->id, $d->id);
    }

    /** Upload a new version: new row, parent_id → previous version, version+1, review reset to pending. */
    public function storeVersion(DocumentVersionRequest $r, int $id, int $docId): JsonResponse
    {
        $this->gateManage($r, 'documents');
        $a = $this->account($id);
        $d = $this->find($r, $a, $docId);
        abort_if($this->has(self::T, 'parent_id') && DB::table(self::T)->where('parent_id', $d->id)->exists(), 422, __('Only the latest version can be replaced.'));
        abort_unless($this->has(self::T, 'parent_id'), 422, __('Document versioning is not available yet.'));
        $row = (array) $d;
        unset($row['id'], $row['reviewed_by'], $row['reviewed_at'], $row['review_note']);
        $row = array_merge($row, $this->storeFile($a, $r->file('file')), [
            'parent_id' => $d->id, 'version' => (int) ($d->version ?? 1) + 1, 'review_status' => 'pending', 'url' => null,
            'created_by' => $r->user()->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $newId = DB::table(self::T)->insertGetId($this->onlyColumns(self::T, $row));
        $this->audit($r, $a, 'document.version', ['document_id' => $newId, 'previous_id' => $d->id, 'version' => $row['version'], 'note' => $r->input('note')]);

        return $this->show($r, $a->id, $newId)->setStatusCode(201);
    }

    /** Deletes the document and all of its earlier versions (files included). */
    public function destroy(Request $r, int $id, int $docId): Response
    {
        $this->gateManage($r, 'documents');
        $a = $this->account($id);
        $d = $this->find($r, $a, $docId);
        $all = $this->chain($d)->prepend($d);
        DB::transaction(function () use ($all) {
            if ($this->has('prj_contracts', 'signed_document_id')) {
                DB::table('prj_contracts')->whereIn('signed_document_id', $all->pluck('id'))->update(['signed_document_id' => null]);
            }
            // children first so parent_id FKs never dangle
            foreach ($all->sortByDesc(fn ($x) => (int) ($x->version ?? 1)) as $x) {
                DB::table(self::T)->where('id', $x->id)->delete();
            }
        });
        foreach ($all as $x) {
            if (! empty($x->file_path)) {
                Storage::disk('local')->delete($x->file_path);
            }
        }
        $this->audit($r, $a, 'document.delete', ['document_id' => $d->id, 'title' => $d->title, 'versions' => $all->count()]);

        return response()->noContent();
    }

    // ---------------------------------------------------------------------

    /** Visible hub row of this customer, or 404 (other customer / hidden by role). */
    private function find(Request $r, CrmAccount $a, int $docId): object
    {
        $d = DB::table(self::T)->where('account_id', $a->id)->where('id', $docId)->first();
        abort_unless($d && $this->visible($r, $d), 404);

        return $d;
    }

    private function visible(Request $r, object $d): bool
    {
        if ($this->access->isAdmin($r->user())) {
            return true;
        }
        $vis = json_decode((string) ($d->visible_to_roles ?? ''), true);

        return ! $vis || (bool) array_intersect($vis, $this->roles($r->user()));
    }

    /** Earlier versions of $d, newest first. */
    private function chain(object $d): Collection
    {
        $out = collect();
        $cur = $d;
        $guard = 0;
        while (! empty($cur->parent_id) && $guard++ < 100) {
            $cur = DB::table(self::T)->where('id', $cur->parent_id)->first();
            if (! $cur) {
                break;
            }
            $out->push($cur);
        }

        return $out;
    }

    private function hubRows(Request $r, CrmAccount $a): Collection
    {
        $q = DB::table(self::T)->where('account_id', $a->id);
        if ($this->has(self::T, 'parent_id')) { // latest version only
            $q->whereNotIn('id', DB::table(self::T)->whereNotNull('parent_id')->where('account_id', $a->id)->select('parent_id'));
        }
        foreach (['review_status', 'contract_id', 'project_id'] as $f) {
            if ($r->filled($f) && $this->has(self::T, $f)) {
                $q->where($f, $r->query($f));
            }
        }
        if ($r->filled('q')) {
            $like = $this->like((string) $r->query('q'));
            $q->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('file_name', 'like', $like)->when($this->has(self::T, 'tags'), fn ($w) => $w->orWhere('tags', 'like', $like)));
        }
        $rows = $q->orderByDesc('id')->limit(2000)->get()->filter(fn ($d) => $this->visible($r, $d));
        $ctx = ['can_manage' => $this->canManage($r, 'documents'), 'refs' => $this->refs($rows)];

        return $rows->map(fn ($d) => DocumentResource::fromRow($d, $ctx));
    }

    /** prj_files of this customer's projects, read-only. */
    private function projectFileRows(Request $r, CrmAccount $a): Collection
    {
        if (! $this->has('prj_files')) {
            return collect();
        }
        $pids = DB::table('prj_projects')->where('customer_account_id', $a->id)
            ->when($r->filled('project_id'), fn ($q) => $q->where('id', (int) $r->query('project_id')))->pluck('name', 'id');
        if ($pids->isEmpty()) {
            return collect();
        }
        $q = DB::table('prj_files')->whereIn('project_id', $pids->keys());
        if ($this->has('prj_files', 'family_id') && $this->has('prj_files', 'version')) { // latest version per family
            $q->whereNotExists(fn ($s) => $s->from('prj_files as n')->whereColumn('n.family_id', 'prj_files.family_id')->whereNotNull('prj_files.family_id')->whereColumn('n.version', '>', 'prj_files.version'));
        }
        if ($r->filled('q')) {
            $q->where('name', 'like', $this->like((string) $r->query('q')));
        }

        return $q->orderByDesc('id')->limit(500)->get()->map(fn ($f) => DocumentResource::fromRow([
            'id' => 'prjfile-'.$f->id, 'source' => 'projects', 'category' => 'deliverable', 'title' => $f->name,
            'file_name' => $f->name, 'file_size' => $f->size_bytes ?? null, 'version' => $f->version ?? 1,
            'project_id' => $f->project_id, 'created_by' => $f->uploaded_by ?? null, 'created_at' => $f->created_at ?? null,
            'file_path' => null, 'url' => '/projects/'.$f->project_id.'?tab=files',
        ], ['refs' => ['projects' => $pids->map(fn ($n, $id) => ['id' => (int) $id, 'name' => $n])->all()]]));
    }

    /** docs_files linked to the customer — requires Phase 0's docs_files.crm_account_id. */
    private function docsFileRows(Request $r, CrmAccount $a): Collection
    {
        if (! $this->has('docs_files', 'crm_account_id')) {
            return collect();
        }
        $q = DB::table('docs_files')->where('crm_account_id', $a->id);
        if ($r->filled('q')) {
            $q->where('name', 'like', $this->like((string) $r->query('q')));
        }

        return $q->orderByDesc('id')->limit(500)->get()->map(fn ($f) => DocumentResource::fromRow([
            'id' => 'docfile-'.$f->id, 'source' => 'docs', 'category' => 'other', 'title' => $f->name,
            'file_name' => $f->name, 'file_mime' => $f->mime_type ?? null, 'file_size' => $f->size ?? null,
            'version' => $f->version ?? 1, 'created_at' => $f->created_at ?? null, 'file_path' => null, 'url' => '/docs?file='.$f->id,
        ]));
    }

    /** Batch-load names for users / contracts / projects referenced by rows. */
    private function refs(Collection $rows): array
    {
        $uids = $rows->flatMap(fn ($d) => [$d->created_by ?? null, $d->reviewed_by ?? null])->filter()->unique()->values();
        $cids = $rows->pluck('contract_id')->filter()->unique()->values();
        $pids = $rows->pluck('project_id')->filter()->unique()->values();

        return [
            'users' => $uids->isEmpty() ? [] : DB::table('users')->whereIn('id', $uids)->get(['id', 'name'])->mapWithKeys(fn ($u) => [$u->id => ['id' => (int) $u->id, 'name' => $u->name]])->all(),
            'contracts' => $cids->isEmpty() ? [] : DB::table('prj_contracts')->whereIn('id', $cids)->get(['id', 'title'])->mapWithKeys(fn ($c) => [$c->id => ['id' => (int) $c->id, 'title' => $c->title]])->all(),
            'projects' => $pids->isEmpty() ? [] : DB::table('prj_projects')->whereIn('id', $pids)->get(['id', 'name'])->mapWithKeys(fn ($p) => [$p->id => ['id' => (int) $p->id, 'name' => $p->name]])->all(),
        ];
    }
}
