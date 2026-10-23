<?php

namespace Modules\Crm\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Crm\Entities\CrmAccount;
use Modules\Crm\Entities\CustomerActivity;
use Modules\Crm\Services\Hub\CustomerActivityBackfill;
use Modules\Crm\Services\Hub\CustomerHubAccess;

/** GET /crm/customers/{id}/hub/timeline — unified, RBAC-filtered, paged Customer 360 timeline. */
class CustomerTimelineController extends Controller
{
    public function __construct(private CustomerHubAccess $access) {}

    public function index(Request $r, int $id): JsonResponse
    {
        abort_unless($this->access->canView($r->user(), 'timeline'), 403, __('Forbidden'));
        $a = CrmAccount::query()->findOrFail($id);
        $r->validate([
            'module' => 'nullable|string|max:200', 'type' => 'nullable|string|max:200', 'kind' => 'nullable|string|max:200',
            'from' => 'nullable|date', 'to' => 'nullable|date', 'q' => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:1|max:100', 'page' => 'nullable|integer|min:1', 'cursor' => 'nullable|string|max:500',
        ]);

        // Accounts created before phase 2 get their history built on first open.
        if (! DB::table('crm_accounts')->where('id', $a->id)->value('activities_backfilled_at')) {
            app(CustomerActivityBackfill::class)->forAccount($a);
        }

        $u = $r->user();
        $hidden = collect(array_keys(CustomerHubAccess::VIEW))->reject(fn ($s) => $this->access->canView($u, $s))->values()->all();
        $list = fn (?string $v) => array_values(array_filter(array_map('trim', explode(',', (string) $v))));

        $q = CustomerActivity::query()->where('account_id', $a->id)
            ->when($hidden, fn ($q) => $q->where(fn ($w) => $w->whereNull('visibility_section')->orWhereNotIn('visibility_section', $hidden)))
            ->when($list($r->query('module')), fn ($q, $m) => $q->whereIn('module', $m))
            ->when($list($r->query('type')), fn ($q, $types) => $q->where(function ($w) use ($types) {
                foreach ($types as $t) {
                    str_ends_with($t, '.*') ? $w->orWhere('type', 'like', substr($t, 0, -1).'%') : $w->orWhere('type', $t);
                }
            }))
            ->when($list($r->query('kind')), fn ($q, $kinds) => $q->where(function ($w) use ($kinds) { // legacy filter: first type segment
                foreach ($kinds as $k) {
                    $w->orWhere('type', $k)->orWhere('type', 'like', $k.'.%');
                }
            }))
            ->when($r->query('from'), fn ($q, $v) => $q->where('occurred_at', '>=', $v))
            ->when($r->query('to'), fn ($q, $v) => $q->where('occurred_at', '<=', strlen($v) <= 10 ? $v.' 23:59:59' : $v))
            ->when($r->query('q'), fn ($q, $v) => $q->where('title', 'like', '%'.$v.'%'))
            ->orderByDesc('occurred_at')->orderByDesc('id');

        $per = (int) $r->query('per_page', 25);
        $total = (clone $q)->count();
        $meta = ['total' => $total, 'per_page' => $per];
        if ($r->filled('page') && ! $r->filled('cursor')) {
            $p = $q->paginate($per);
            $items = $p->items();
            $meta += ['current_page' => $p->currentPage(), 'last_page' => $p->lastPage()];
        } else {
            $p = $q->cursorPaginate($per);
            $items = $p->items();
            $meta += ['next_cursor' => $p->nextCursor()?->encode(), 'prev_cursor' => $p->previousCursor()?->encode()];
        }

        return response()->json(['data' => array_map(fn (CustomerActivity $e) => [
            'id' => $e->id, 'module' => $e->module, 'type' => $e->type, 'kind' => explode('.', $e->type)[0],
            'title' => $e->title, 'occurred_at' => $e->occurred_at?->toIso8601String(), 'date' => $e->occurred_at?->toDateTimeString(),
            'subject_type' => $e->subject_type, 'subject_id' => $e->subject_id, 'ref' => $e->meta['ref'] ?? null,
            'status' => $e->meta['status'] ?? null, 'meta' => $e->meta, 'actor_id' => $e->actor_id,
        ], $items), 'meta' => $meta]);
    }
}
