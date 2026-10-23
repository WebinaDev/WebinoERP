<?php

namespace Modules\Crm\Http\Controllers\Customer360;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Crm\Entities\CrmAccount;
use Modules\Crm\Entities\CrmContact;
use Modules\Crm\Entities\CrmMessage;
use Modules\Crm\Entities\CrmMessageTemplate;
use Modules\Crm\Services\CrmOutboundMessenger;
use Modules\Crm\Services\Hub\CustomerResolver;

/** /crm/customers/{id}/hub/sms (PHASE3_API.md §3). */
class HubSmsController extends Hub360Base
{
    public function index(Request $r, int $id): JsonResponse
    {
        $a = $this->account($r, $id, 'sms');
        $r->validate(['status' => 'nullable|string|max:20', 'q' => 'nullable|string|max:100', 'per_page' => 'nullable|integer|min:1|max:100', 'page' => 'nullable|integer|min:1']);
        $per = min(100, max(1, (int) $r->query('per_page', 20)));
        $p = $this->query($a)
            ->when($r->filled('status'), fn ($q) => $q->where('status', $r->query('status')))
            ->when($r->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('body', 'like', '%'.$r->query('q').'%')->orWhere('to_address', 'like', '%'.$r->query('q').'%')))
            ->orderByDesc('id')->paginate($per);
        $users = DB::table('users')->whereIn('id', collect($p->items())->pluck('created_by')->filter()->unique())->pluck('name', 'id');

        return response()->json(['data' => collect($p->items())->map(fn ($m) => $this->row($m, $users))->values(), 'meta' => [
            'total' => $p->total(), 'per_page' => $p->perPage(), 'current_page' => $p->currentPage(), 'last_page' => $p->lastPage(),
            'can' => ['send' => $this->access->canManage($r->user(), 'sms')],
            'recipients' => $this->recipients($a),
            'templates' => CrmMessageTemplate::query()->where('channel', 'sms')->where('is_active', true)->orderBy('name')->get(['id', 'name', 'body']),
            'opt_out' => (bool) ($a->marketing_sms_opt_out ?? false),
        ]]);
    }

    public function send(Request $r, int $id, CrmOutboundMessenger $messenger): JsonResponse
    {
        $a = $this->account($r, $id, 'sms');
        abort_unless($this->access->canManage($r->user(), 'sms'), 403, __('Forbidden'));
        $v = $r->validate(['to' => 'nullable|string|max:20', 'contact_id' => 'nullable|integer', 'body' => 'required_without:template_id|nullable|string|max:900',
            'template_id' => 'nullable|integer', 'purpose' => 'nullable|in:transactional,marketing']);
        if (($v['purpose'] ?? 'transactional') === 'marketing' && ! empty($a->marketing_sms_opt_out)) {
            abort(response()->json(['message' => __('Customer opted out of marketing SMS'), 'errors' => ['purpose' => ['opt_out']]], 422));
        }
        $relType = 'account';
        $relId = $a->id;
        $to = $v['to'] ?? null;
        if (! empty($v['contact_id'])) {
            $c = CrmContact::query()->where('account_id', $a->id)->findOrFail($v['contact_id']);
            [$relType, $relId, $to] = ['contact', $c->id, $to ?: $c->mobile];
        }
        $to = CustomerResolver::normMobile($to ?: $a->mobile);
        if (! $to) {
            abort(response()->json(['message' => __('messages.recipient_address_is_missing'), 'errors' => ['to' => ['missing']]], 422));
        }
        $tpl = ! empty($v['template_id']) ? CrmMessageTemplate::query()->where('channel', 'sms')->findOrFail($v['template_id']) : null;
        $body = (string) ($v['body'] ?? '') !== '' ? $v['body'] : (string) $tpl?->body;
        $m = $messenger->send('sms', $relType, $relId, $to, $body, null, $tpl?->id, $r->user()->id, ['name' => (string) $a->name]);
        if (empty($m->crm_account_id)) {
            $m->forceFill(['crm_account_id' => $a->id])->saveQuietly();
        }

        return response()->json(['data' => $this->row($m->fresh(), collect([$r->user()->id => $r->user()->name]))], 201);
    }

    public static function sendTo(CrmAccount $a, string $body, ?int $userId = null): ?CrmMessage
    {
        $to = CustomerResolver::normMobile($a->mobile);

        return $to ? app(CrmOutboundMessenger::class)->send('sms', 'account', $a->id, $to, $body, null, null, $userId, ['name' => (string) $a->name]) : null;
    }

    private function query(CrmAccount $a)
    {
        $contacts = CrmContact::withTrashed()->where('account_id', $a->id)->pluck('id');

        return CrmMessage::query()->where('channel', 'sms')->where(fn ($w) => $w->where('crm_account_id', $a->id)
            ->orWhere(fn ($x) => $x->whereIn('related_type', ['account', CrmAccount::class])->where('related_id', $a->id))
            ->orWhere(fn ($x) => $x->whereIn('related_type', ['contact', CrmContact::class])->whereIn('related_id', $contacts)));
    }

    private function row(CrmMessage $m, $users): array
    {
        return ['id' => $m->id, 'to' => $m->to_address, 'body' => $m->body, 'status' => $m->status, 'provider' => $m->provider, 'error' => $m->error,
            'template_id' => $m->template_id, 'related_type' => $m->related_type, 'related_id' => $m->related_id, 'created_at' => $this->iso($m->created_at),
            'created_by' => $m->created_by ? ['id' => $m->created_by, 'name' => $users[$m->created_by] ?? null] : null];
    }

    private function recipients(CrmAccount $a): array
    {
        $out = [];
        if ($a->mobile) {
            $out[] = ['kind' => 'account', 'id' => $a->id, 'label' => (string) $a->name, 'mobile' => $a->mobile];
        }
        foreach (CrmContact::query()->where('account_id', $a->id)->whereNotNull('mobile')->get() as $c) {
            $out[] = ['kind' => 'contact', 'id' => $c->id, 'label' => trim($c->first_name.' '.$c->last_name), 'mobile' => $c->mobile];
        }

        return $out;
    }
}
