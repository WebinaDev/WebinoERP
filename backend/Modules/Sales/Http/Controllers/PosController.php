<?php

namespace Modules\Sales\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Modules\Accounting\Entities\AccProduct;
use Modules\Accounting\Entities\AccProductVariant;
use Modules\Crm\Entities\CrmAccount;
use Modules\Crm\Entities\CrmCompany;
use Modules\Sales\Entities\PosOrder;
use Modules\Sales\Entities\PosRegister;
use Modules\Sales\Entities\PosSession;
use Modules\Sales\Services\Pos\PosService;

class PosController extends Controller
{
    public function __construct(private readonly PosService $pos) {}

    /* ---------------- overview ---------------- */

    public function overview(Request $request): JsonResponse
    {
        $today = now()->toDateString();
        $daily = $this->pos->dailyReport($today, null);
        $current = $this->currentSession($request);

        return response()->json(['data' => [
            'today' => $daily['summary'],
            'by_method' => $daily['by_method'],
            'open_sessions' => PosSession::query()->where('status', 'open')->with(['register:id,name', 'user:id,name'])->orderBy('opened_at')->get(),
            'registers_count' => PosRegister::query()->where('is_active', true)->count(),
            'current_session' => $current ? $current->load('register') : null,
            'recent_orders' => PosOrder::query()->with('user:id,name')->latest('id')->limit(8)->get(),
            'can_manage' => $this->canManage($request),
        ]]);
    }

    /* ---------------- registers ---------------- */

    public function registers(): JsonResponse
    {
        return response()->json(['data' => PosRegister::query()->orderBy('name')->get()->map(function (PosRegister $r) {
            $r->setAttribute('open_session', PosSession::query()->where('register_id', $r->id)->where('status', 'open')->with('user:id,name')->first());

            return $r;
        })]);
    }

    public function storeRegister(Request $request): JsonResponse
    {
        $this->requireManage($request);
        $register = PosRegister::query()->create($this->validateRegister($request, true));

        return response()->json(['data' => $register], 201);
    }

    public function updateRegister(Request $request, int $id): JsonResponse
    {
        $this->requireManage($request);
        $register = PosRegister::query()->findOrFail($id);
        $register->update($this->validateRegister($request, false));

        return response()->json(['data' => $register->fresh()]);
    }

    public function warehouses(): JsonResponse
    {
        $rows = Schema::hasTable('scm_warehouses') ? DB::table('scm_warehouses')->where('is_active', true)->orderBy('name')->get(['id', 'name']) : collect();

        return response()->json(['data' => $rows]);
    }

    /* ---------------- sessions ---------------- */

    public function sessions(Request $request): JsonResponse
    {
        $q = PosSession::query()->with(['register:id,name', 'user:id,name'])->latest('id');
        if (! $this->canManage($request)) {
            $q->where('user_id', $request->user()->id);
        }
        foreach (['register_id', 'status'] as $f) {
            if ($request->filled($f)) {
                $q->where($f, $request->input($f));
            }
        }
        if ($request->filled('date')) {
            $q->whereDate('opened_at', $request->input('date'));
        }

        return response()->json(['data' => $q->paginate(min((int) $request->input('per_page', 25), 100))]);
    }

    public function current(Request $request): JsonResponse
    {
        $s = $this->currentSession($request);
        if (! $s) {
            return response()->json(['data' => null]);
        }
        $s->load('register');

        return response()->json(['data' => $s, 'report' => $this->pos->sessionReport($s)]);
    }

    public function openSession(Request $request): JsonResponse
    {
        $data = $request->validate([
            'register_id' => 'required|exists:pos_registers,id',
            'opening_cash' => 'nullable|numeric|min:0',
            'note' => 'nullable|string|max:500',
        ]);
        $session = $this->pos->openSession(PosRegister::query()->findOrFail($data['register_id']), $request->user()->id, (float) ($data['opening_cash'] ?? 0), $data['note'] ?? null);

        return response()->json(['data' => $session->load('register')], 201);
    }

    public function showSession(Request $request, int $id): JsonResponse
    {
        $s = $this->sessionFor($request, $id);

        return response()->json(['data' => $s->load(['register', 'user:id,name', 'cashMovements']), 'report' => $s->status === 'closed' && $s->totals ? $s->totals + ['z_number' => $s->z_number] : $this->pos->sessionReport($s)]);
    }

    public function closeSession(Request $request, int $id): JsonResponse
    {
        $s = $this->sessionFor($request, $id);
        $data = $request->validate([
            'counted_cash' => 'nullable|numeric|min:0',
            'denominations' => 'nullable|array',
            'denominations.*' => 'integer|min:0',
            'note' => 'nullable|string|max:1000',
        ]);
        if (! isset($data['counted_cash']) && empty($data['denominations'])) {
            $data['counted_cash'] = 0;
        }
        $closed = $this->pos->closeSession($s, isset($data['counted_cash']) ? (float) $data['counted_cash'] : null, $data['denominations'] ?? null, $data['note'] ?? null, $request->user()->id);

        return response()->json(['data' => $closed, 'report' => $closed->totals + ['z_number' => $closed->z_number]]);
    }

    public function cashMovement(Request $request, int $id): JsonResponse
    {
        $s = $this->sessionFor($request, $id);
        $data = $request->validate([
            'type' => 'required|in:in,out',
            'amount' => 'required|numeric|gt:0',
            'reason' => 'nullable|string|max:255',
        ]);

        return response()->json(['data' => $this->pos->addCashMovement($s, $data['type'], (float) $data['amount'], $data['reason'] ?? null, $request->user()->id)], 201);
    }

    /* ---------------- catalog ---------------- */

    public function products(Request $request): JsonResponse
    {
        $warehouseId = null;
        if ($s = $this->currentSession($request)) {
            $warehouseId = $s->register?->warehouse_id;
        }
        if ($request->filled('barcode')) {
            $code = trim((string) $request->input('barcode'));
            $variant = AccProductVariant::query()->where('is_active', true)->where(fn ($q) => $q->where('barcode', $code)->orWhere('sku', $code))->first();
            $product = $variant ? AccProduct::query()->find($variant->product_id) : AccProduct::query()->where('barcode', $code)->first();

            return response()->json(['data' => $product ? [$this->productRow($product, $warehouseId, $variant?->id)] : []]);
        }
        $q = AccProduct::query()->orderBy('name');
        if ($request->filled('q')) {
            $term = '%'.trim((string) $request->input('q')).'%';
            $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('barcode', 'like', $term)->orWhere('category', 'like', $term)
                ->orWhereIn('id', AccProductVariant::query()->where('barcode', 'like', $term)->orWhere('sku', 'like', $term)->orWhere('name', 'like', $term)->select('product_id')));
        }
        if ($request->filled('category')) {
            $q->where('category', $request->input('category'));
        }
        $rows = $q->limit(min((int) $request->input('limit', 60), 200))->get()->map(fn ($p) => $this->productRow($p, $warehouseId, null));
        $categories = AccProduct::query()->whereNotNull('category')->where('category', '!=', '')->distinct()->orderBy('category')->pluck('category');

        return response()->json(['data' => $rows, 'categories' => $categories]);
    }

    public function variants(int $id): JsonResponse
    {
        AccProduct::query()->findOrFail($id);

        return response()->json(['data' => AccProductVariant::query()->where('product_id', $id)->orderBy('id')->get()]);
    }

    public function storeVariant(Request $request, int $id): JsonResponse
    {
        $this->requireManage($request);
        AccProduct::query()->findOrFail($id);
        $data = $this->validateVariant($request);

        return response()->json(['data' => AccProductVariant::query()->create($data + ['product_id' => $id])], 201);
    }

    public function updateVariant(Request $request, int $variantId): JsonResponse
    {
        $this->requireManage($request);
        $v = AccProductVariant::query()->findOrFail($variantId);
        $v->update($this->validateVariant($request));

        return response()->json(['data' => $v->fresh()]);
    }

    /* ---------------- customers ---------------- */

    public function customers(Request $request): JsonResponse
    {
        $q = CrmAccount::query()->orderBy('name');
        if ($request->filled('q')) {
            $term = '%'.trim((string) $request->input('q')).'%';
            $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('mobile', 'like', $term)->orWhere('phone', 'like', $term)->orWhere('account_code', 'like', $term));
        }

        return response()->json(['data' => $q->limit(20)->get(['id', 'name', 'mobile', 'phone', 'account_code', 'default_discount_pct'])]);
    }

    public function storeCustomer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:191',
            'mobile' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:160',
        ]);
        if (! empty($data['mobile']) && ($existing = CrmAccount::query()->where('mobile', $data['mobile'])->first())) {
            return response()->json(['data' => $existing->only(['id', 'name', 'mobile', 'phone', 'account_code', 'default_discount_pct'])]);
        }
        $acc = CrmAccount::query()->create($data + ['type' => 'customer', 'customer_kind' => 'individual', 'source' => 'pos', 'created_by' => $request->user()->id]);

        return response()->json(['data' => $acc->only(['id', 'name', 'mobile', 'phone', 'account_code', 'default_discount_pct'])], 201);
    }

    /* ---------------- orders ---------------- */

    public function orders(Request $request): JsonResponse
    {
        $q = PosOrder::query()->with(['user:id,name', 'register:id,name'])->latest('id');
        if (! $this->canManage($request)) {
            $q->where('user_id', $request->user()->id);
        }
        foreach (['session_id', 'register_id', 'type', 'status', 'customer_id'] as $f) {
            if ($request->filled($f)) {
                $q->where($f, $request->input($f));
            }
        }
        if ($request->filled('date')) {
            $q->whereDate('created_at', $request->input('date'));
        }
        if ($request->filled('q')) {
            $term = '%'.trim((string) $request->input('q')).'%';
            $q->where(fn ($w) => $w->where('number', 'like', $term)->orWhere('customer_name', 'like', $term));
        }

        return response()->json(['data' => $q->paginate(min((int) $request->input('per_page', 25), 100))]);
    }

    public function checkout(Request $request): JsonResponse
    {
        $session = $this->currentSession($request);
        if (! $session) {
            return response()->json(['message' => __('pos.no_open_session')], 422);
        }
        $data = $request->validate([
            'lines' => 'required|array|min:1|max:300',
            'lines.*.product_id' => 'nullable|integer',
            'lines.*.variant_id' => 'nullable|integer',
            'lines.*.name' => 'nullable|string|max:255',
            'lines.*.quantity' => 'required|numeric|gt:0',
            'lines.*.unit_price' => 'nullable|numeric|min:0',
            'lines.*.discount_amount' => 'nullable|numeric|min:0',
            'lines.*.discount_percent' => 'nullable|numeric|min:0|max:100',
            'payments' => 'required|array|min:1|max:10',
            'payments.*.method' => ['required', Rule::in(PosRegister::METHODS)],
            'payments.*.amount' => 'required|numeric|min:0',
            'payments.*.reference' => 'nullable|string|max:100',
            'customer_id' => 'nullable|exists:crm_accounts,id',
            'customer_name' => 'nullable|string|max:191',
            'discount_amount' => 'nullable|numeric|min:0',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string|max:1000',
        ]);
        // Changing a catalog price at the till is a manager action.
        if (! $this->canManage($request)) {
            foreach ($data['lines'] as $i => $l) {
                if (isset($l['unit_price']) && (! empty($l['product_id']) || ! empty($l['variant_id']))) {
                    unset($data['lines'][$i]['unit_price']);
                }
            }
        }
        $order = $this->pos->checkout($session, $data, $request->user()->id);

        return response()->json(['data' => $order, 'receipt' => $this->receipt($order)], 201);
    }

    public function showOrder(Request $request, int $id): JsonResponse
    {
        $order = PosOrder::query()->with(['lines', 'payments', 'user:id,name', 'register', 'refunds:id,number,refund_of_id,total,created_at', 'refundOf:id,number'])->findOrFail($id);
        if (! $this->canManage($request) && $order->user_id !== $request->user()->id) {
            abort(403);
        }

        return response()->json(['data' => $order, 'receipt' => $this->receipt($order)]);
    }

    public function refund(Request $request, int $id): JsonResponse
    {
        $this->requireManage($request);
        $session = $this->currentSession($request);
        if (! $session) {
            return response()->json(['message' => __('pos.no_open_session')], 422);
        }
        $data = $request->validate([
            'lines' => 'required|array|min:1',
            'lines.*.line_id' => 'required|integer',
            'lines.*.quantity' => 'required|numeric|gt:0',
            'payments' => 'nullable|array',
            'payments.*.method' => ['required', Rule::in(PosRegister::METHODS)],
            'payments.*.amount' => 'required|numeric|min:0',
            'restock' => 'nullable|boolean',
            'reason' => 'nullable|string|max:500',
        ]);
        $order = PosOrder::query()->findOrFail($id);
        $refund = $this->pos->refund($order, $session, $data['lines'], $data['payments'] ?? null, (bool) ($data['restock'] ?? true), $data['reason'] ?? null, $request->user()->id);

        return response()->json(['data' => $refund, 'receipt' => $this->receipt($refund)], 201);
    }

    public function dailyReport(Request $request): JsonResponse
    {
        $data = $request->validate(['date' => 'nullable|date', 'register_id' => 'nullable|integer']);
        if (! $this->canManage($request)) {
            abort(403, __('pos.forbidden_manage'));
        }

        return response()->json(['data' => $this->pos->dailyReport($data['date'] ?? now()->toDateString(), isset($data['register_id']) ? (int) $data['register_id'] : null)]);
    }

    /* ---------------- helpers ---------------- */

    private function receipt(PosOrder $order): array
    {
        $order->loadMissing(['lines', 'payments', 'register', 'user:id,name']);
        $company = $order->company_id ? CrmCompany::query()->find($order->company_id) : CrmCompany::query()->where('is_default', true)->first();

        return [
            'company' => $company ? array_filter($company->only(['name', 'legal_name', 'brand_name', 'national_id', 'economic_code', 'registration_number', 'address', 'phone', 'website'])) : null,
            'header' => $order->register?->receipt_header,
            'footer' => $order->register?->receipt_footer,
            'register' => $order->register?->name,
            'cashier' => $order->user?->name,
        ];
    }

    private function productRow(AccProduct $p, ?int $warehouseId, ?int $variantId): array
    {
        $variants = AccProductVariant::query()->where('product_id', $p->id)->where('is_active', true)->orderBy('id')->get(['id', 'name', 'sku', 'barcode', 'sell_price']);
        $stock = null;
        if ($warehouseId && $p->inventory_controlled && Schema::hasTable('scm_warehouse_stock')) {
            $stock = (float) DB::table('scm_warehouse_stock')->where('warehouse_id', $warehouseId)->where('product_id', $p->id)->value('quantity');
        }

        return [
            'id' => $p->id,
            'name' => $p->name,
            'barcode' => $p->barcode,
            'category' => $p->category,
            'unit' => $p->unit,
            'sell_price' => (float) $p->sell_price,
            'inventory_controlled' => (bool) $p->inventory_controlled,
            'stock' => $stock,
            'variants' => $variants,
            'matched_variant_id' => $variantId,
        ];
    }

    private function validateRegister(Request $request, bool $creating): array
    {
        $req = $creating ? 'required' : 'sometimes|required';

        return $request->validate([
            'name' => $req.'|string|max:160',
            'code' => 'nullable|string|max:32',
            'company_id' => 'nullable|exists:crm_companies,id',
            'branch_id' => 'nullable|integer',
            'warehouse_id' => 'nullable|integer',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'tax_inclusive' => 'nullable|boolean',
            'allow_negative_stock' => 'nullable|boolean',
            'payment_methods' => 'nullable|array',
            'payment_methods.*' => Rule::in(PosRegister::METHODS),
            'receipt_header' => 'nullable|string|max:1000',
            'receipt_footer' => 'nullable|string|max:1000',
            'is_active' => 'nullable|boolean',
        ]);
    }

    private function validateVariant(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:191',
            'sku' => 'nullable|string|max:100',
            'barcode' => 'nullable|string|max:100',
            'sell_price' => 'nullable|numeric|min:0',
            'attributes' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);
    }

    private function currentSession(Request $request): ?PosSession
    {
        return PosSession::query()->with('register')->where('user_id', $request->user()->id)->where('status', 'open')->latest('id')->first();
    }

    private function sessionFor(Request $request, int $id): PosSession
    {
        $s = PosSession::query()->findOrFail($id);
        if ($s->user_id !== $request->user()->id && ! $this->canManage($request)) {
            abort(403, __('pos.not_your_session'));
        }

        return $s;
    }

    private function canManage(Request $request): bool
    {
        $u = $request->user();
        try {
            return $u && ($u->hasRole('system_manager') || $u->can('sales.pos.manage'));
        } catch (\Throwable) {
            return false;
        }
    }

    private function requireManage(Request $request): void
    {
        if (! $this->canManage($request)) {
            abort(403, __('pos.forbidden_manage'));
        }
    }
}
