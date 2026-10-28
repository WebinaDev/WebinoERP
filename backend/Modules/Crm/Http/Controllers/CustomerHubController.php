<?php

namespace Modules\Crm\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\MutationAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Modules\Crm\Services\Hub\CustomerSiteSync;
use Modules\Crm\Services\Hub\CustomerSuspension;
use Modules\Crm\Services\Hub\SiteLinkClient;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Modules\Crm\Entities\CrmAccount;
use Modules\Crm\Entities\CrmContact;
use Modules\Crm\Services\CrmOutboundMessenger;
use Modules\Crm\Services\Hub\CustomerHubAccess;
use Modules\Crm\Services\Hub\CustomerHubService;
use Modules\Projects\Entities\PrjTicket;
use Modules\Projects\Entities\Project;
use Modules\Sales\Entities\SalesInvoice;

class CustomerHubController extends Controller
{
    public function __construct(private CustomerHubService $hub, private CustomerHubAccess $access) {}

    private function account(int $id): CrmAccount
    {
        return CrmAccount::query()->findOrFail($id);
    }

    private function gate(Request $r, string $section): void
    {
        abort_unless($this->access->canView($r->user(), $section), 403, __('Forbidden'));
    }

    private function gateManage(Request $r, string $action): void
    {
        abort_unless($this->access->canManage($r->user(), $action), 403, __('Forbidden'));
    }

    private function audit(Request $r, CrmAccount $a, string $action, array $changes = []): void
    {
        DB::table('crm_customer_audit')->insert([
            'account_id' => $a->id, 'user_id' => $r->user()?->id, 'action' => $action,
            'changes' => json_encode($changes, JSON_UNESCAPED_UNICODE), 'ip' => $r->ip(), 'created_at' => now(),
        ]);
        MutationAudit::record($r->user()?->id, 'crm', 'customer_hub.'.$action, CrmAccount::class, $a->id, $changes);
        if (! in_array($action, ['hub.export', 'document.download'], true)) {
            try {
                event(new \Modules\Crm\Events\CustomerHubUpdated($a->id, $action, $r->user()?->id));
            } catch (\Throwable) {
                // broadcasting is best-effort; clients also poll
            }
        }
    }

    private function roles(User $u): array
    {
        try {
            return $u->getRoleNames()->all();
        } catch (\Throwable) {
            return [];
        }
    }

    // ───────────── read ─────────────

    public function hub(Request $r, int $id): JsonResponse
    {
        $a = $this->account($id);
        $u = $r->user();
        $sections = $this->access->sections($u);
        abort_if($sections === [], 403, __('Forbidden'));
        $finance = in_array('billing', $sections, true);

        $data = [
            'header' => $this->hub->header($a),
            'kpis' => $this->hub->kpis($a, $finance),
            'health' => in_array('analytics', $sections, true) || $finance ? $this->hub->health($a) : null,
            'alerts' => $this->hub->alerts($a),
            'sections' => $sections,
            'actions' => $this->access->actions($u),
        ];
        if (in_array('identity', $sections, true)) {
            $data['identity'] = $this->hub->identity($a);
        }
        if (in_array('licenses', $sections, true)) {
            $data['licenses_preview'] = $this->hub->licenses($a)->take(10)->values();
        }
        if (in_array('notes', $sections, true)) {
            $data['notes'] = $this->hub->notes($a);
        }
        if (! $finance) {
            $data['alerts'] = array_values(array_filter($data['alerts'], fn ($x) => ($x['code'] ?? '') !== 'overdue_debt'));
        }

        return response()->json(['data' => $data]);
    }

    private function paginate(Request $r, Collection $items): array
    {
        $per = max(5, min(100, (int) $r->query('per_page', 20)));
        $page = max(1, (int) $r->query('page', 1));
        $p = new LengthAwarePaginator($items->forPage($page, $per)->values(), $items->count(), $per, $page);

        return ['data' => $p->items(), 'meta' => ['current_page' => $p->currentPage(), 'last_page' => $p->lastPage(), 'per_page' => $per, 'total' => $p->total()]];
    }

    public function section(Request $r, int $id, string $section): JsonResponse
    {
        $a = $this->account($id);
        $map = [
            'identity' => ['identity', fn () => $this->hub->identity($a)],
            'assets' => ['assets', fn () => $this->hub->assets($a)],
            'licenses' => ['licenses', fn () => $this->paginate($r, $this->hub->licenses($a))],
            'billing' => ['billing', fn () => $this->hub->billing($a)],
            'invoices' => ['billing', fn () => $this->paginate($r, $this->filterStatus($r, $this->hub->invoices($a)->map(fn ($i) => (array) $i)))],
            'payments' => ['billing', fn () => $this->paginate($r, $this->hub->payments($a))],
            'wallet_ledger' => ['billing', fn () => $this->paginate($r, $this->hub->walletLedger($a))],
            'sales' => ['sales', fn () => $this->hub->sales($a)],
            'tickets' => ['support', fn () => $this->paginate($r, $this->filterStatus($r, $this->hub->tickets($a)->map(fn ($t) => (array) $t)))],
            'communications' => ['support', fn () => $this->paginate($r, $this->hub->communications($a))],
            'timeline' => ['timeline', fn () => $this->paginate($r, $this->filterKind($r, $this->timelineFor($r, $a)))],
            'usage' => ['usage', fn () => $this->hub->usage($a)],
            'site_panel' => ['site_panel', fn () => app(SiteLinkClient::class)->customerPanels($a, $r->boolean('fresh'))],
            'documents' => ['documents', fn () => $this->paginate($r, $this->hub->documents($a, $this->roles($r->user()), $this->access->isAdmin($r->user()))
                ->map(function ($d) {
                    unset($d['file_path']);

                    return $d;
                }))],
            'access' => ['access', fn () => $this->hub->access($a)],
            'relations' => ['relations', fn () => $this->hub->relations($a)],
            'notes' => ['notes', fn () => $this->hub->notes($a)],
            'analytics' => ['analytics', fn () => $this->hub->analytics($a, $this->access->canView($r->user(), 'billing'))],
        ];
        abort_unless(isset($map[$section]), 404);
        [$gate, $fn] = $map[$section];
        $this->gate($r, $gate);
        $out = $fn();

        return response()->json(isset($out['meta']) ? $out : ['data' => $out]);
    }

    /** Timeline only contains entries from sections the caller may see. */
    private function timelineFor(Request $r, CrmAccount $a): Collection
    {
        $u = $r->user();
        $deny = [];
        if (! $this->access->canView($u, 'billing')) {
            $deny = array_merge($deny, ['payment', 'invoice']);
        }
        if (! $this->access->canView($u, 'support')) {
            $deny[] = 'ticket';
        }

        return $this->hub->timeline($a)->reject(fn ($e) => in_array($e['kind'], $deny, true))->values();
    }

    private function filterStatus(Request $r, Collection $c): Collection
    {
        $s = $r->query('status');

        return $s ? $c->where('status', $s)->values() : $c;
    }

    private function filterKind(Request $r, Collection $c): Collection
    {
        $k = array_filter(explode(',', (string) $r->query('kind', '')));

        return $k ? $c->whereIn('kind', $k)->values() : $c;
    }

    // ───────────── identity ─────────────

    public function updateProfile(Request $r, int $id): JsonResponse
    {
        $this->gateManage($r, 'profile');
        $a = $this->account($id);
        $data = $r->validate([
            'name' => 'sometimes|string|max:255', 'brand_name' => 'nullable|string|max:200',
            'customer_kind' => 'nullable|in:individual,legal,partner,vip',
            'hub_status' => 'nullable|in:active,dormant,debtor,blocked,prospect',
            'national_id' => 'nullable|string|max:30', 'economic_code' => 'nullable|string|max:30', 'registration_no' => 'nullable|string|max:30',
            'postal_code' => 'nullable|string|max:20', 'address_line' => 'nullable|string|max:2000', 'city' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100', 'country' => 'nullable|string|max:100', 'website' => 'nullable|string|max:255',
            'logo_url' => 'nullable|string|max:1000', 'industry' => 'nullable|string|max:100',
            'kyc_status' => 'nullable|in:none,pending,verified,rejected', 'kyc_note' => 'nullable|string|max:2000',
            'preferred_language' => 'nullable|string|max:10', 'preferred_channel' => 'nullable|in:phone,sms,email,whatsapp',
            'timezone' => 'nullable|string|max:64', 'marketing_sms_opt_out' => 'nullable|boolean',
            'referrer_account_id' => 'nullable|integer|exists:crm_accounts,id', 'partner_account_id' => 'nullable|integer|exists:crm_accounts,id',
            'parent_id' => 'nullable|integer|exists:crm_accounts,id', 'credit_limit' => 'nullable|numeric|min:0',
            'allowed_ips' => 'nullable|array', 'allowed_ips.*' => 'ip',
        ]);
        foreach (['referrer_account_id', 'partner_account_id', 'parent_id'] as $k) {
            abort_if(isset($data[$k]) && (int) $data[$k] === $a->id, 422, 'Self reference');
        }
        if (($data['kyc_status'] ?? null) === 'verified' && $a->kyc_status !== 'verified') {
            $data['kyc_verified_at'] = now();
        }
        $before = array_intersect_key($a->getAttributes(), $data);
        $a->fill($data)->save();
        $sensitive = array_intersect(array_keys($data), ['kyc_status', 'national_id', 'economic_code', 'allowed_ips', 'credit_limit', 'hub_status']);
        $this->audit($r, $a, $sensitive ? 'profile.sensitive_update' : 'profile.update', ['before' => $before, 'after' => $data]);

        return response()->json(['data' => $this->hub->identity($a->fresh())]);
    }

    public function saveContact(Request $r, int $id, ?int $contactId = null): JsonResponse
    {
        $this->gateManage($r, 'contacts');
        $a = $this->account($id);
        $data = $r->validate([
            'first_name' => ($contactId ? 'sometimes' : 'required').'|string|max:100', 'last_name' => 'nullable|string|max:100',
            'job_title' => 'nullable|string|max:100', 'contact_role' => 'nullable|in:manager,finance,technical,support,other',
            'mobile' => 'nullable|string|max:20', 'phone' => 'nullable|string|max:20', 'email' => 'nullable|email|max:255',
            'whatsapp' => 'nullable|string|max:20', 'is_primary' => 'nullable|boolean',
        ]);
        $c = $contactId ? CrmContact::query()->where('account_id', $a->id)->findOrFail($contactId) : new CrmContact(['account_id' => $a->id, 'created_by' => $r->user()->id]);
        if (! $contactId) {
            $data['last_name'] ??= '';
        }
        $c->fill($data)->save();
        if (! empty($data['is_primary'])) {
            CrmContact::query()->where('account_id', $a->id)->where('id', '!=', $c->id)->update(['is_primary' => false]);
        }
        $this->audit($r, $a, $contactId ? 'contact.update' : 'contact.create', ['contact_id' => $c->id]);

        return response()->json(['data' => $c->fresh()], $contactId ? 200 : 201);
    }

    public function deleteContact(Request $r, int $id, int $contactId): JsonResponse
    {
        $this->gateManage($r, 'contacts');
        $a = $this->account($id);
        CrmContact::query()->where('account_id', $a->id)->findOrFail($contactId)->delete();
        $this->audit($r, $a, 'contact.delete', ['contact_id' => $contactId]);

        return response()->json(['ok' => true]);
    }

    // ───────────── documents ─────────────

    public const DOC_MAX_KB = 10240;

    public const DOC_MIMES = 'pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,zip';

    public function storeDocument(Request $r, int $id): JsonResponse
    {
        $this->gateManage($r, 'documents');
        $a = $this->account($id);
        if (is_string($r->input('visible_to_roles'))) { // multipart sends a JSON/CSV string
            $raw = (string) $r->input('visible_to_roles');
            $r->merge(['visible_to_roles' => json_decode($raw, true) ?? array_values(array_filter(array_map('trim', explode(',', $raw))))]);
        }
        $data = $r->validate([
            'category' => 'required|in:national_card,gazette,enamad,permit,contract,proposal,invoice,brief,deliverable,other',
            'title' => 'required|string|max:255', 'url' => 'nullable|string|max:1000', 'core_document_id' => 'nullable|integer',
            'file' => 'nullable|file|max:'.self::DOC_MAX_KB.'|mimes:'.self::DOC_MIMES,
            'expires_at' => 'nullable|date', 'visible_to_roles' => 'nullable|array', 'visible_to_roles.*' => 'string|max:64',
        ]);
        $file = null;
        if ($r->hasFile('file')) {
            $up = $r->file('file');
            $ext = strtolower($up->guessExtension() ?: $up->getClientOriginalExtension() ?: 'bin');
            $path = $up->storeAs('crm/customer-docs/'.$a->id, Str::uuid()->toString().'.'.$ext, 'local'); // private disk
            $file = ['file_path' => $path, 'file_name' => mb_substr($up->getClientOriginalName(), 0, 255), 'file_mime' => $up->getMimeType(), 'file_size' => $up->getSize()];
        }
        $docId = DB::table('crm_customer_documents')->insertGetId([
            'account_id' => $a->id, 'category' => $data['category'], 'title' => $data['title'], 'url' => $data['url'] ?? null,
            'core_document_id' => $data['core_document_id'] ?? null, 'expires_at' => $data['expires_at'] ?? null,
            'visible_to_roles' => isset($data['visible_to_roles']) ? json_encode(array_values($data['visible_to_roles'])) : null,
            'review_status' => 'pending', 'created_by' => $r->user()->id, 'created_at' => now(), 'updated_at' => now(),
        ] + ($file ?? []));
        $this->audit($r, $a, 'document.create', ['document_id' => $docId, 'category' => $data['category'], 'file' => $file['file_name'] ?? null]);

        return response()->json(['data' => $this->publicDoc(DB::table('crm_customer_documents')->find($docId))], 201);
    }

    private function publicDoc(?object $d): ?array
    {
        if (! $d) {
            return null;
        }
        $arr = (array) $d;
        $arr['has_file'] = ! empty($d->file_path);
        unset($arr['file_path']);

        return $arr;
    }

    /** Issue a 5-minute signed link — only if the caller can see this document (role visibility). */
    public function documentLink(Request $r, int $id, int $docId): JsonResponse
    {
        $this->gate($r, 'documents');
        $a = $this->account($id);
        $visible = $this->hub->documents($a, $this->roles($r->user()), $this->access->isAdmin($r->user()))
            ->first(fn ($d) => (string) ($d['id'] ?? '') === (string) $docId);
        abort_unless($visible && ! empty($visible['file_path']), 404);
        $this->audit($r, $a, 'document.download', ['document_id' => $docId]);

        return response()->json(['data' => [
            'url' => URL::temporarySignedRoute('crm.hub.document.file', now()->addMinutes(5), ['id' => $a->id, 'docId' => $docId, 'u' => $r->user()->id]),
            'expires_in' => 300,
        ]]);
    }

    public function downloadDocument(Request $r, int $id, int $docId): StreamedResponse
    {
        $d = DB::table('crm_customer_documents')->where('account_id', $id)->where('id', $docId)->first();
        abort_unless($d && $d->file_path && Storage::disk('local')->exists($d->file_path), 404);

        return Storage::disk('local')->download($d->file_path, $d->file_name ?: basename($d->file_path), [
            'Content-Type' => $d->file_mime ?: 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ]);
    }

    public function updateDocument(Request $r, int $id, int $docId): JsonResponse
    {
        $this->gateManage($r, 'documents');
        $a = $this->account($id);
        $data = $r->validate([
            'review_status' => 'sometimes|in:pending,approved,rejected', 'review_note' => 'nullable|string|max:2000',
            'expires_at' => 'nullable|date', 'title' => 'sometimes|string|max:255',
            'visible_to_roles' => 'nullable|array', 'visible_to_roles.*' => 'string|max:64',
        ]);
        if (array_key_exists('visible_to_roles', $data)) {
            $data['visible_to_roles'] = $data['visible_to_roles'] !== null ? json_encode(array_values($data['visible_to_roles'])) : null;
        }
        $n = DB::table('crm_customer_documents')->where('account_id', $a->id)->where('id', $docId)->update($data + ['updated_at' => now()]);
        abort_if($n === 0, 404);
        if (isset($data['review_status'])) {
            $this->audit($r, $a, 'document.review', ['document_id' => $docId, 'status' => $data['review_status']]);
        }
        if (($data['review_status'] ?? null) && in_array(DB::table('crm_customer_documents')->where('id', $docId)->value('category'), ['national_card', 'gazette'], true)) {
            // KYC status follows approval of identity documents.
            $a->update(['kyc_status' => $data['review_status'] === 'approved' ? 'verified' : ($data['review_status'] === 'rejected' ? 'rejected' : 'pending'),
                'kyc_verified_at' => $data['review_status'] === 'approved' ? now() : null]);
        }

        return response()->json(['data' => $this->publicDoc(DB::table('crm_customer_documents')->find($docId))]);
    }

    public function deleteDocument(Request $r, int $id, int $docId): JsonResponse
    {
        $this->gateManage($r, 'documents');
        $a = $this->account($id);
        $path = DB::table('crm_customer_documents')->where('account_id', $a->id)->where('id', $docId)->value('file_path');
        abort_if(DB::table('crm_customer_documents')->where('account_id', $a->id)->where('id', $docId)->delete() === 0, 404);
        if ($path) {
            Storage::disk('local')->delete($path);
        }
        $this->audit($r, $a, 'document.delete', ['document_id' => $docId]);

        return response()->json(['ok' => true]);
    }

    // ───────────── assets ─────────────

    public function storeDomain(Request $r, int $id): JsonResponse
    {
        $this->gateManage($r, 'assets');
        $a = $this->account($id);
        $data = $r->validate([
            'domain' => ['required', 'string', 'max:255', 'regex:/^(?!-)[a-z0-9\-\.]+\.[a-z\x{0600}-\x{06FF}]{2,}$/iu'],
            'registrar' => 'nullable|string|max:100', 'registered_at' => 'nullable|date', 'expires_at' => 'nullable|date',
            'nameservers' => 'nullable|array', 'nameservers.*' => 'string|max:255', 'site_provision_id' => 'nullable|integer', 'auto_renew' => 'nullable|boolean',
        ]);
        $domain = strtolower($data['domain']);
        abort_if(DB::table('crm_customer_domains')->where('account_id', $a->id)->where('domain', $domain)->exists(), 422, 'Domain already registered for this customer');
        $did = DB::table('crm_customer_domains')->insertGetId([
            'account_id' => $a->id, 'domain' => $domain, 'registrar' => $data['registrar'] ?? null, 'registered_at' => $data['registered_at'] ?? null,
            'expires_at' => $data['expires_at'] ?? null, 'nameservers' => isset($data['nameservers']) ? json_encode($data['nameservers']) : null,
            'site_provision_id' => $data['site_provision_id'] ?? null, 'auto_renew' => (bool) ($data['auto_renew'] ?? false),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->audit($r, $a, 'domain.create', ['domain' => $domain]);

        return response()->json(['data' => DB::table('crm_customer_domains')->find($did)], 201);
    }

    public function deleteDomain(Request $r, int $id, int $domainId): JsonResponse
    {
        $this->gateManage($r, 'assets');
        $a = $this->account($id);
        abort_if(DB::table('crm_customer_domains')->where('account_id', $a->id)->where('id', $domainId)->delete() === 0, 404);
        $this->audit($r, $a, 'domain.delete', ['domain_id' => $domainId]);

        return response()->json(['ok' => true]);
    }

    public function storeService(Request $r, int $id): JsonResponse
    {
        $this->gateManage($r, 'assets');
        $a = $this->account($id);
        $data = $r->validate([
            'kind' => 'required|in:hosting,vps,gateway,sms,accounting,shipping,api_key,other', 'name' => 'required|string|max:200',
            'provider' => 'nullable|string|max:100', 'plan' => 'nullable|string|max:100', 'ip' => 'nullable|ip', 'status' => 'nullable|string|max:20',
            'site_domain' => 'nullable|string|max:255', 'secret' => 'nullable|string|max:2000', 'renews_at' => 'nullable|date', 'last_backup_at' => 'nullable|date',
        ]);
        // Secrets are never stored by the hub — only their last 4 characters for identification.
        $last4 = isset($data['secret']) ? mb_substr($data['secret'], -4) : null;
        unset($data['secret']);
        $sid = DB::table('crm_customer_services')->insertGetId($data + ['account_id' => $a->id, 'key_last4' => $last4, 'status' => $data['status'] ?? 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->audit($r, $a, 'service.create', ['service_id' => $sid, 'kind' => $data['kind']]);

        return response()->json(['data' => DB::table('crm_customer_services')->find($sid)], 201);
    }

    public function deleteService(Request $r, int $id, int $serviceId): JsonResponse
    {
        $this->gateManage($r, 'assets');
        $a = $this->account($id);
        abort_if(DB::table('crm_customer_services')->where('account_id', $a->id)->where('id', $serviceId)->delete() === 0, 404);
        $this->audit($r, $a, 'service.delete', ['service_id' => $serviceId]);

        return response()->json(['ok' => true]);
    }

    // ───────────── relations / notes / onboarding / usage ─────────────

    public function storeRelation(Request $r, int $id): JsonResponse
    {
        $this->gateManage($r, 'relations');
        $a = $this->account($id);
        $data = $r->validate(['related_account_id' => 'required|integer|exists:crm_accounts,id', 'relation_type' => 'required|in:holding,related,partner,referral', 'note' => 'nullable|string|max:255']);
        abort_if((int) $data['related_account_id'] === $a->id, 422, 'Self relation');
        $rid = DB::table('crm_customer_relations')->insertOrIgnore([$data + ['account_id' => $a->id, 'created_at' => now(), 'updated_at' => now()]]);
        $this->audit($r, $a, 'relation.create', $data);

        return response()->json(['data' => $this->hub->relations($a)], 201);
    }

    public function deleteRelation(Request $r, int $id, int $relId): JsonResponse
    {
        $this->gateManage($r, 'relations');
        $a = $this->account($id);
        abort_if(DB::table('crm_customer_relations')->where('id', $relId)->where(fn ($q) => $q->where('account_id', $a->id)->orWhere('related_account_id', $a->id))->delete() === 0, 404);

        return response()->json(['ok' => true]);
    }

    public function storeAlert(Request $r, int $id): JsonResponse
    {
        $this->gateManage($r, 'notes');
        $a = $this->account($id);
        $data = $r->validate(['kind' => 'required|in:pinned_note,risk', 'severity' => 'nullable|in:info,warning,critical', 'body' => 'required|string|max:5000']);
        $aid = DB::table('crm_customer_alerts')->insertGetId($data + ['severity' => $data['severity'] ?? 'info', 'account_id' => $a->id, 'created_by' => $r->user()->id, 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['data' => DB::table('crm_customer_alerts')->find($aid)], 201);
    }

    public function resolveAlert(Request $r, int $id, int $alertId): JsonResponse
    {
        $this->gateManage($r, 'notes');
        $a = $this->account($id);
        abort_if(DB::table('crm_customer_alerts')->where('account_id', $a->id)->where('id', $alertId)->update(['resolved_at' => now(), 'updated_at' => now()]) === 0, 404);

        return response()->json(['ok' => true]);
    }

    public function storeOnboarding(Request $r, int $id): JsonResponse
    {
        $this->gateManage($r, 'notes');
        $a = $this->account($id);
        $data = $r->validate(['title' => 'nullable|string|max:255', 'preset' => 'nullable|boolean']);
        $titles = ! empty($data['preset'])
            ? ['تکمیل اطلاعات هویتی و KYC', 'ثبت دامنه و اتصال DNS', 'راه‌اندازی سایت', 'صدور لایسنس', 'تنظیم درگاه پرداخت', 'شارژ کیف پول پیامک', 'آموزش پنل', 'تحویل نهایی']
            : [$data['title'] ?? abort(422, 'title required')];
        $sort = (int) DB::table('crm_customer_onboarding_items')->where('account_id', $a->id)->max('sort');
        foreach ($titles as $t) {
            DB::table('crm_customer_onboarding_items')->insert(['account_id' => $a->id, 'title' => $t, 'sort' => ++$sort, 'created_at' => now(), 'updated_at' => now()]);
        }

        return response()->json(['data' => $this->hub->notes($a)['onboarding']], 201);
    }

    public function toggleOnboarding(Request $r, int $id, int $itemId): JsonResponse
    {
        $this->gateManage($r, 'notes');
        $a = $this->account($id);
        $row = DB::table('crm_customer_onboarding_items')->where('account_id', $a->id)->where('id', $itemId)->first();
        abort_unless($row, 404);
        DB::table('crm_customer_onboarding_items')->where('id', $itemId)->update(['done_at' => $row->done_at ? null : now(), 'done_by' => $row->done_at ? null : $r->user()->id, 'updated_at' => now()]);

        return response()->json(['data' => DB::table('crm_customer_onboarding_items')->find($itemId)]);
    }

    public function storeUsageMetric(Request $r, int $id): JsonResponse
    {
        $this->gateManage($r, 'assets');
        $a = $this->account($id);
        $data = $r->validate(['metric' => 'required|in:sms_sent,orders,traffic_mb,storage_mb,bandwidth_mb,panel_users,cpu_pct,ram_mb', 'value' => 'required|numeric', 'period' => 'nullable|date', 'site_provision_id' => 'nullable|integer']);
        DB::table('crm_customer_usage_metrics')->insert($data + ['account_id' => $a->id, 'period' => $data['period'] ?? now()->toDateString(), 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['ok' => true], 201);
    }

    // ───────────── action bar ─────────────

    public function addNote(Request $r, int $id): JsonResponse
    {
        $this->gateManage($r, 'notes');
        $a = $this->account($id);
        $data = $r->validate(['body' => 'required|string|max:10000', 'type' => 'nullable|in:note,call,meeting', 'pin' => 'nullable|boolean']);
        $nid = DB::table('crm_activities')->insertGetId([
            'type' => $data['type'] ?? 'note', 'subject' => Str::limit($data['body'], 80), 'description' => $data['body'],
            'related_model' => CrmAccount::class, 'related_id' => $a->id, 'created_by' => $r->user()->id, 'completed_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        if (! empty($data['pin'])) {
            DB::table('crm_customer_alerts')->insert(['account_id' => $a->id, 'kind' => 'pinned_note', 'severity' => 'info', 'body' => $data['body'], 'created_by' => $r->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        }

        return response()->json(['data' => ['id' => $nid]], 201);
    }

    public function createInvoice(Request $r, int $id): JsonResponse
    {
        $this->gateManage($r, 'invoice');
        $a = $this->account($id);
        $data = $r->validate([
            'items' => 'required|array|min:1', 'items.*.title' => 'required|string|max:255', 'items.*.price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0', 'items.*.desc' => 'nullable|string|max:1000',
            'issue_date' => 'nullable|date', 'project_id' => 'nullable|integer', 'notes' => 'nullable|string|max:5000', 'status' => 'nullable|in:draft,unpaid',
        ]);
        $sub = collect($data['items'])->sum(fn ($i) => (float) $i['price']);
        $disc = collect($data['items'])->sum(fn ($i) => (float) ($i['discount'] ?? 0));
        $number = 'INV-'.now()->format('ymd').'-'.strtoupper(Str::random(5));
        $inv = SalesInvoice::query()->create([
            'number' => $number, 'customer_id' => $a->id, 'customer_name' => $a->name, 'items' => $data['items'],
            'subtotal' => $sub, 'discount' => $disc, 'total' => max(0, $sub - $disc), 'status' => $data['status'] ?? 'unpaid',
            'issue_date' => $data['issue_date'] ?? now()->toDateString(), 'project_id' => $data['project_id'] ?? null,
            'notes' => $data['notes'] ?? null, 'created_by' => $r->user()->id,
        ]);
        $this->audit($r, $a, 'invoice.create', ['invoice_id' => $inv->id, 'total' => $inv->total]);

        return response()->json(['data' => $inv], 201);
    }

    public function recordPayment(Request $r, int $id): JsonResponse
    {
        $this->gateManage($r, 'payment');
        $a = $this->account($id);
        $data = $r->validate([
            'amount' => 'required|numeric|min:1', 'method' => 'required|in:cash,card_to_card,bank_transfer,cheque,pos,other',
            'reference' => 'nullable|string|max:120', 'paid_at' => 'nullable|date', 'invoice_id' => 'nullable|integer', 'note' => 'nullable|string|max:2000',
        ]);
        $inv = null;
        if (! empty($data['invoice_id'])) {
            $inv = SalesInvoice::query()->where('customer_id', $a->id)->findOrFail($data['invoice_id']);
        }
        $pid = DB::transaction(function () use ($r, $a, $data, $inv) {
            $pid = DB::table('crm_activities')->insertGetId([
                'type' => 'payment', 'subject' => 'پرداخت دستی '.number_format((float) $data['amount']), 'description' => $data['note'] ?? null,
                'related_model' => CrmAccount::class, 'related_id' => $a->id, 'created_by' => $r->user()->id,
                'completed_at' => $data['paid_at'] ?? now(), 'meta' => json_encode(['amount' => (float) $data['amount'], 'method' => $data['method'], 'reference' => $data['reference'] ?? null, 'invoice_id' => $inv?->id]),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            if ($inv) {
                $paidSoFar = $this->hub->payments($a)->where('invoice_id', $inv->id)->where('paid', true)->sum('amount') + (float) $data['amount'];
                if ($paidSoFar + 0.001 >= (float) $inv->total) {
                    $inv->update(['status' => 'paid', 'payment_method' => $data['method']]);
                }
            }

            return $pid;
        });
        $this->audit($r, $a, 'payment.manual', ['activity_id' => $pid, 'amount' => (float) $data['amount'], 'invoice_id' => $inv?->id]);
        \Modules\Crm\Events\Customer\PaymentRecorded::dispatch($a->id, $pid, [
            'subject_type' => 'manual_payment', 'amount' => (float) $data['amount'], 'method' => $data['method'], 'invoice_id' => $inv?->id,
        ], $r->user()->id, (string) ($data['paid_at'] ?? now()), 'پرداخت دستی '.number_format((float) $data['amount']));

        return response()->json(['data' => ['id' => 'mp-'.$pid, 'invoice_status' => $inv?->fresh()->status]], 201);
    }

    public function issueLicense(Request $r, int $id): JsonResponse
    {
        $this->gateManage($r, 'license');
        $a = $this->account($id);
        $data = $r->validate([
            'domain' => 'required|string|max:255', 'product' => 'nullable|string|max:64', 'license_type' => 'required|in:monthly,yearly,perpetual,trial',
            'expires_at' => 'nullable|date', 'usage_limits' => 'nullable|array', 'feature_flags' => 'nullable|array', 'max_users' => 'nullable|integer|min:1',
        ]);
        $domain = strtolower($data['domain']);
        $product = $data['product'] ?? 'webino';
        abort_if(DB::table('core_licenses')->where('domain', $domain)->where('product', $product)->exists(), 422, 'License already exists for this domain/product');
        $exp = $data['expires_at'] ?? match ($data['license_type']) {
            'monthly' => now()->addMonth(), 'yearly' => now()->addYear(), 'trial' => now()->addDays(14), default => null,
        };
        $lid = DB::table('core_licenses')->insertGetId([
            'license_key' => strtoupper(Str::random(8).'-'.Str::random(8).'-'.Str::random(8)), 'domain' => $domain, 'product' => $product,
            'project_name' => $a->name, 'status' => 'active', 'start_date' => now()->toDateString(), 'expires_at' => $exp,
            'max_users' => $data['max_users'] ?? null, 'crm_account_id' => $a->id, 'license_type' => $data['license_type'],
            'usage_limits' => isset($data['usage_limits']) ? json_encode($data['usage_limits']) : null,
            'feature_flags' => isset($data['feature_flags']) ? json_encode($data['feature_flags']) : null,
            'created_by' => $r->user()->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->audit($r, $a, 'license.issue', ['license_id' => $lid, 'domain' => $domain]);

        return response()->json(['data' => $this->freshHub($a)->licenses($a)->firstWhere('id', $lid)], 201);
    }

    public function renewLicense(Request $r, int $id, int $licenseId): JsonResponse
    {
        $this->gateManage($r, 'license');
        $a = $this->account($id);
        abort_unless($this->hub->licenses($a)->firstWhere('id', $licenseId), 404);
        $data = $r->validate(['months' => 'required|integer|min:1|max:60', 'expires_at' => 'nullable|date']);
        $row = DB::table('core_licenses')->find($licenseId);
        $base = $row->expires_at && now()->lt($row->expires_at) ? \Carbon\Carbon::parse($row->expires_at) : now();
        $newExp = $data['expires_at'] ?? $base->copy()->addMonths($data['months']);
        $meta = json_decode((string) ($row->meta ?? ''), true) ?: [];
        $meta['renewals'][] = ['at' => now()->toDateTimeString(), 'by' => $r->user()->id, 'from' => $row->expires_at, 'to' => (string) $newExp, 'months' => $data['months']];
        DB::table('core_licenses')->where('id', $licenseId)->update(['expires_at' => $newExp, 'status' => 'active', 'meta' => json_encode($meta), 'updated_at' => now()]);
        $this->audit($r, $a, 'license.renew', ['license_id' => $licenseId, 'to' => (string) $newExp]);

        return response()->json(['data' => $this->freshHub($a)->licenses($a)->firstWhere('id', $licenseId)]);
    }

    private function freshHub(CrmAccount $a): CustomerHubService
    {
        return app(CustomerHubService::class);
    }

    public function createTicket(Request $r, int $id): JsonResponse
    {
        $this->gateManage($r, 'ticket');
        $a = $this->account($id);
        $data = $r->validate(['subject' => 'required|string|max:255', 'body' => 'nullable|string|max:20000', 'priority' => 'nullable|in:low,normal,medium,high,urgent', 'department' => 'nullable|string|max:50', 'project_id' => 'nullable|integer', 'assignee_id' => 'nullable|integer|exists:users,id']);
        $t = PrjTicket::query()->create($data + ['status' => 'open', 'customer_account_id' => $a->id, 'priority' => $data['priority'] ?? 'normal', 'body' => $data['body'] ?? '']);
        $this->audit($r, $a, 'ticket.create', ['ticket_id' => $t->id]);

        return response()->json(['data' => $t], 201);
    }

    public function createProject(Request $r, int $id): JsonResponse
    {
        $this->gateManage($r, 'project');
        $a = $this->account($id);
        $data = $r->validate(['name' => 'required|string|max:255', 'description' => 'nullable|string|max:10000', 'start_date' => 'nullable|date', 'due_date' => 'nullable|date', 'budget_amount' => 'nullable|numeric|min:0', 'manager_user_id' => 'nullable|integer|exists:users,id']);
        $p = Project::query()->create($data + ['status' => 'active', 'customer_account_id' => $a->id, 'created_by' => $r->user()->id]);
        $this->audit($r, $a, 'project.create', ['project_id' => $p->id]);

        return response()->json(['data' => $p], 201);
    }

    public function sendMessage(Request $r, int $id, CrmOutboundMessenger $messenger): JsonResponse
    {
        $u = $r->user();
        abort_unless($this->access->isAdmin($u) || $u->can('crm.messages.manage') || $u->can('crm.accounts.manage'), 403);
        $a = $this->account($id);
        $data = $r->validate(['channel' => 'required|in:sms,email', 'to' => 'required|string|max:255', 'body' => 'required|string|max:5000', 'subject' => 'nullable|string|max:255', 'marketing' => 'nullable|boolean']);
        abort_if($data['channel'] === 'sms' && ! empty($data['marketing']) && $a->marketing_sms_opt_out, 422, 'Customer opted out of marketing SMS');
        $msg = $messenger->send($data['channel'], 'account', $a->id, $data['to'], $data['body'], $data['subject'] ?? null, null, $u->id, $messenger->contextFor('account', $a->id));
        MutationAudit::record($u->id, 'crm', 'message.send', get_class($msg), $msg->id, ['channel' => $data['channel'], 'account_id' => $a->id]);

        return response()->json(['data' => $msg], 201);
    }

    public function changeOwner(Request $r, int $id): JsonResponse
    {
        $this->gateManage($r, 'owner');
        $a = $this->account($id);
        $data = $r->validate(['owner_id' => 'required|integer|exists:users,id']);
        $from = $a->owner_id;
        $a->update(['owner_id' => $data['owner_id']]);
        $this->audit($r, $a, 'owner.change', ['from' => $from, 'to' => (int) $data['owner_id']]);

        return response()->json(['data' => $this->hub->header($a->fresh())]);
    }

    public function suspend(Request $r, int $id, CustomerSuspension $svc): JsonResponse
    {
        $this->gateManage($r, 'suspend');
        $a = $this->account($id);
        $data = $r->validate(['reason' => 'required|string|max:1000', 'confirm' => 'accepted']);
        $result = $svc->suspend($a, $data['reason'], $r->user()?->id);
        $this->audit($r, $a, 'account.suspend', ['reason' => $data['reason']] + $result);

        return response()->json(['data' => $this->hub->header($a->fresh()) + ['action_result' => $result]]);
    }

    public function activate(Request $r, int $id, CustomerSuspension $svc): JsonResponse
    {
        $this->gateManage($r, 'suspend');
        $a = $this->account($id);
        $r->validate(['confirm' => 'accepted']);
        $result = $svc->activate($a);
        $this->audit($r, $a, 'account.activate', $result);

        return response()->json(['data' => $this->hub->header($a->fresh()) + ['action_result' => $result]]);
    }

    /** Pull usage from the customer's sites now (same path as the scheduled crm:hub-sync-sites). */
    public function syncUsage(Request $r, int $id, CustomerSiteSync $sync): JsonResponse
    {
        $this->gateManage($r, 'assets');
        $a = $this->account($id);
        $stats = $sync->run($a->id, true);
        $this->audit($r, $a, 'usage.sync', $stats);

        return response()->json(['data' => $this->hub->usage($a) + ['sync_result' => $stats]]);
    }

    /** CSV export of the sections the caller may view (UTF-8 BOM so Excel renders Persian). */
    public function exportCsv(Request $r, int $id): StreamedResponse
    {
        $a = $this->account($id);
        $sections = $this->access->sections($r->user());
        abort_if($sections === [], 403, __('Forbidden'));
        $this->audit($r, $a, 'hub.export', ['format' => 'csv']);
        $h = $this->hub;
        $rows = [];
        $add = function (string $section, iterable $items) use (&$rows) {
            foreach ($items as $it) {
                $it = (array) $it;
                $flat = [];
                foreach ($it as $k => $v) {
                    if (is_scalar($v) || $v === null) {
                        $flat[$k] = $v;
                    }
                }
                $rows[] = [$section, json_encode($flat, JSON_UNESCAPED_UNICODE)];
            }
        };
        $add('header', [$h->header($a)]);
        if (in_array('identity', $sections, true)) {
            $add('identity', [$h->identity($a)]);
        }
        if (in_array('licenses', $sections, true)) {
            $add('licenses', $h->licenses($a));
        }
        if (in_array('billing', $sections, true)) {
            $add('invoices', $h->invoices($a));
            $add('payments', $h->payments($a));
        }
        if (in_array('support', $sections, true)) {
            $add('tickets', $h->tickets($a));
        }
        if (in_array('documents', $sections, true)) {
            $add('documents', $h->documents($a, $this->roles($r->user()), $this->access->isAdmin($r->user()))->map(function ($d) {
                unset($d['file_path']);

                return $d;
            }));
        }
        $name = 'customer-'.$a->id.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['section', 'data']);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
