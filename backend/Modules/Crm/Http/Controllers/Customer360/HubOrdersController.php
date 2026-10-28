<?php

namespace Modules\Crm\Http\Controllers\Customer360;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Crm\Entities\CrmAccount;
use Modules\Crm\Entities\CrmBasalamOrder;
use Modules\Crm\Services\Hub\CustomerResolver;
use Modules\Marketplace\Entities\MarketplaceBasalamConnection;

/** /crm/customers/{id}/hub/orders + /hub/basalam/stores (PHASE3_API.md §1). */
class HubOrdersController extends Hub360Base
{
    public function index(Request $r, int $id): JsonResponse
    {
        $a = $this->account($r, $id, 'orders');
        $r->validate(['source' => 'nullable|in:all,marketplace,basalam', 'status' => 'nullable|string|max:32', 'q' => 'nullable|string|max:100', 'paid' => 'nullable|in:0,1', 'per_page' => 'nullable|integer|min:1|max:100', 'page' => 'nullable|integer|min:1']);
        $rows = $this->rows($a, $this->money($r));
        $src = $r->query('source', 'all');
        $all = $rows;
        $rows = $rows->when($src !== 'all', fn ($c) => $c->where('source', $src))
            ->when($r->filled('status'), fn ($c) => $c->where('status', $r->query('status')))
            ->when($r->filled('paid'), fn ($c) => $c->where('paid', $r->query('paid') === '1'))
            ->when($r->filled('q'), function ($c) use ($r) {
                $q = mb_strtolower((string) $r->query('q'));

                return $c->filter(fn ($o) => str_contains(mb_strtolower($o['number'].' '.$o['buyer_name'].' '.$o['buyer_mobile']), $q));
            })->values();
        [$data, $meta] = $this->page($r, $rows->map(fn ($o) => collect($o)->except('items')->all()));
        $money = $this->money($r);
        $meta['kpis'] = ['count' => $all->count(), 'paid_count' => $all->where('paid', true)->count(),
            'total_amount' => $money ? round((float) $all->where('paid', true)->sum(fn ($o) => (float) $o['total']), 2) : null,
            'basalam_count' => $all->where('source', 'basalam')->count(), 'marketplace_count' => $all->where('source', 'marketplace')->count()];
        $meta['statuses'] = $all->pluck('status')->filter()->unique()->sort()->values()->all();
        $meta['can'] = ['link_basalam' => $this->access->canManage($r->user(), 'basalam')];

        return response()->json(['data' => $data, 'meta' => $meta]);
    }

    public function show(Request $r, int $id, string $ref): JsonResponse
    {
        $a = $this->account($r, $id, 'orders');
        $o = $this->rows($a, $this->money($r))->firstWhere('ref', $ref);
        abort_unless($o, 404);

        return response()->json(['data' => $o]);
    }

    private function rows(CrmAccount $a, bool $money): Collection
    {
        $out = collect();
        if (Schema::hasTable('marketplace_orders')) {
            $orders = DB::table('marketplace_orders')->where('crm_account_id', $a->id)->orderByDesc('id')->limit(1000)->get();
            $items = Schema::hasTable('marketplace_order_items') && $orders->isNotEmpty()
                ? DB::table('marketplace_order_items')->whereIn('order_id', $orders->pluck('id'))->get()->groupBy('order_id') : collect();
            $names = Schema::hasTable('marketplace_modules') ? DB::table('marketplace_modules')->pluck('name', 'id') : collect();
            foreach ($orders as $o) {
                $its = collect($items->get($o->id, []))->map(fn ($i) => ['title' => $names[$i->module_id] ?? ($i->module_slug ?? null) ?? '—',
                    'qty' => (int) ($i->quantity ?? 1), 'unit_price' => $this->m($money, $i->unit_price ?? null),
                    'total' => $this->m($money, isset($i->unit_price) ? $i->unit_price * ($i->quantity ?? 1) : null), 'sku' => $i->module_slug ?? null])->values()->all();
                $out->push(['ref' => 'mo-'.$o->id, 'id' => $o->id, 'source' => 'marketplace', 'number' => $o->order_number, 'status' => $o->status,
                    'total' => $this->m($money, $o->total), 'currency' => 'IRR', 'paid' => ! empty($o->paid_at) || $o->status === 'paid',
                    'paid_at' => $this->iso($o->paid_at ?? null), 'ordered_at' => $this->iso($o->created_at), 'items_count' => count($its),
                    'buyer_name' => null, 'buyer_mobile' => null, 'gateway' => $o->payment_gateway ?? null, 'payment_ref' => $o->payment_ref ?? null,
                    'store' => null, 'items' => $its]);
            }
        }
        $conns = MarketplaceBasalamConnection::query()->pluck('site_url', 'id');
        foreach (CrmBasalamOrder::query()->where('crm_account_id', $a->id)->orderByDesc('ordered_at')->limit(1000)->get() as $b) {
            $its = collect($b->items ?? [])->map(fn ($i) => ['title' => (string) ($i['title'] ?? $i['name'] ?? '—'), 'qty' => (int) ($i['qty'] ?? $i['quantity'] ?? 1),
                'unit_price' => $this->m($money, $i['unit_price'] ?? $i['price'] ?? null), 'total' => $this->m($money, $i['total'] ?? null), 'sku' => $i['sku'] ?? null])->values()->all();
            $out->push(['ref' => 'bs-'.$b->id, 'id' => $b->id, 'source' => 'basalam', 'number' => (string) $b->external_id, 'status' => $b->status,
                'total' => $this->m($money, $b->total), 'currency' => 'IRR', 'paid' => $b->paid_at !== null, 'paid_at' => $this->iso($b->paid_at),
                'ordered_at' => $this->iso($b->ordered_at ?? $b->created_at), 'items_count' => (int) ($b->items_count ?: count($its)),
                'buyer_name' => $b->buyer_name, 'buyer_mobile' => $b->buyer_mobile, 'gateway' => 'basalam', 'payment_ref' => null,
                'store' => $b->connection_id ? ['connection_id' => $b->connection_id, 'site_url' => $conns[$b->connection_id] ?? null] : null, 'items' => $its]);
        }

        return $out->sortByDesc(fn ($o) => (string) $o['ordered_at'])->values();
    }

    // ---- Basalam stores ----

    private function store(MarketplaceBasalamConnection $c): array
    {
        return ['connection_id' => $c->id, 'site_url' => $c->site_url, 'vendor_id' => $c->vendor_id, 'status' => $c->status,
            'connected_at' => $this->iso($c->connected_at), 'last_seen_at' => $this->iso($c->last_seen_at), 'linked' => $c->crm_account_id !== null];
    }

    public function stores(Request $r, int $id): JsonResponse
    {
        $a = $this->account($r, $id, 'orders');
        $linked = MarketplaceBasalamConnection::query()->where('crm_account_id', $a->id)->get();
        $candidates = MarketplaceBasalamConnection::query()->whereNull('crm_account_id')->get()
            ->filter(fn ($c) => (int) CustomerResolver::byDomain((string) $c->site_url) === $a->id);

        return response()->json(['data' => $linked->map(fn ($c) => $this->store($c))->values(),
            'meta' => ['candidates' => $candidates->map(fn ($c) => $this->store($c))->values(), 'can' => ['link' => $this->access->canManage($r->user(), 'basalam')]]]);
    }

    public function link(Request $r, int $id): JsonResponse
    {
        $a = $this->account($r, $id, 'orders');
        abort_unless($this->access->canManage($r->user(), 'basalam'), 403, __('Forbidden'));
        $v = $r->validate(['connection_id' => 'required|integer', 'force' => 'nullable|boolean']);
        $c = MarketplaceBasalamConnection::query()->findOrFail($v['connection_id']);
        abort_if($c->crm_account_id && $c->crm_account_id !== $a->id && empty($v['force']), 409, __('Store is linked to another customer'));
        $c->forceFill(['crm_account_id' => $a->id])->saveQuietly();
        CrmBasalamOrder::query()->where('connection_id', $c->id)->whereNull('crm_account_id')->update(['crm_account_id' => $a->id]);

        return response()->json(['data' => $this->store($c->fresh())]);
    }

    public function unlink(Request $r, int $id, int $connectionId): Response
    {
        $a = $this->account($r, $id, 'orders');
        abort_unless($this->access->canManage($r->user(), 'basalam'), 403, __('Forbidden'));
        $c = MarketplaceBasalamConnection::query()->where('crm_account_id', $a->id)->findOrFail($connectionId);
        $c->forceFill(['crm_account_id' => null])->saveQuietly();

        return response()->noContent();
    }
}
