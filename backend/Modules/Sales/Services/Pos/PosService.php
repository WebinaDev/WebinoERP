<?php

namespace Modules\Sales\Services\Pos;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Entities\AccProduct;
use Modules\Accounting\Entities\AccProductVariant;
use Modules\Sales\Entities\PosCashMovement;
use Modules\Sales\Entities\PosOrder;
use Modules\Sales\Entities\PosOrderLine;
use Modules\Sales\Entities\PosPayment;
use Modules\Sales\Entities\PosRegister;
use Modules\Sales\Entities\PosSession;
use Modules\Sales\Entities\SalesInvoice;
use Modules\Sales\Entities\SalesInvoiceLine;
use Modules\Scm\Services\StockLedgerEngine;

/**
 * Point of sale engine: shifts with cash count, checkout with split payments,
 * stock movements through the SCM stock ledger, a sales invoice and a journal entry
 * per order, refunds and the Z report.
 */
class PosService
{
    public function __construct(
        private readonly PosAccountingService $accounting,
        private readonly StockLedgerEngine $stock,
    ) {}

    /* ---------------------------------------------------------------- */
    /* Sessions                                                         */
    /* ---------------------------------------------------------------- */

    public function openSession(PosRegister $register, int $userId, float $openingCash, ?string $note): PosSession
    {
        if (! $register->is_active) {
            throw ValidationException::withMessages(['register_id' => __('pos.register_inactive')]);
        }

        return DB::transaction(function () use ($register, $userId, $openingCash, $note) {
            PosRegister::query()->whereKey($register->id)->lockForUpdate()->first();
            if (PosSession::query()->where('register_id', $register->id)->where('status', 'open')->exists()) {
                throw ValidationException::withMessages(['register_id' => __('pos.register_busy')]);
            }
            if (PosSession::query()->where('user_id', $userId)->where('status', 'open')->exists()) {
                throw ValidationException::withMessages(['register_id' => __('pos.user_has_open_session')]);
            }

            return PosSession::query()->create([
                'register_id' => $register->id,
                'user_id' => $userId,
                'status' => 'open',
                'opened_at' => now(),
                'opening_cash' => round(max(0, $openingCash), 2),
                'opening_note' => $note,
            ]);
        });
    }

    public function addCashMovement(PosSession $session, string $type, float $amount, ?string $reason, int $userId): PosCashMovement
    {
        $this->assertOpen($session);
        if ($type === 'out' && $amount > $this->expectedCash($session) + 0.001) {
            throw ValidationException::withMessages(['amount' => __('pos.not_enough_cash')]);
        }

        return PosCashMovement::query()->create([
            'session_id' => $session->id,
            'type' => $type,
            'amount' => round($amount, 2),
            'reason' => $reason,
            'user_id' => $userId,
        ]);
    }

    /**
     * @param  array<string, int|float>|null  $denominations  face value => count
     */
    public function closeSession(PosSession $session, ?float $countedCash, ?array $denominations, ?string $note, int $userId): PosSession
    {
        $this->assertOpen($session);

        return DB::transaction(function () use ($session, $countedCash, $denominations, $note, $userId) {
            PosRegister::query()->whereKey($session->register_id)->lockForUpdate()->first();
            if ($denominations) {
                $sum = 0.0;
                foreach ($denominations as $face => $count) {
                    $sum += (float) $face * max(0, (int) $count);
                }
                $countedCash ??= $sum;
            }
            $countedCash = round((float) $countedCash, 2);
            $report = $this->sessionReport($session);
            $expected = $report['cash']['expected'];
            $z = (int) PosSession::query()->where('register_id', $session->register_id)->max('z_number') + 1;

            $session->update([
                'status' => 'closed',
                'closed_at' => now(),
                'expected_cash' => $expected,
                'counted_cash' => $countedCash,
                'cash_difference' => round($countedCash - $expected, 2),
                'denominations' => $denominations,
                'totals' => $report,
                'z_number' => $z,
                'closing_note' => $note,
                'closed_by' => $userId,
            ]);

            return $session->fresh(['register', 'user']);
        });
    }

    public function expectedCash(PosSession $session): float
    {
        return $this->sessionReport($session)['cash']['expected'];
    }

    /** Z / X report for one session. */
    public function sessionReport(PosSession $session): array
    {
        $orders = PosOrder::query()->where('session_id', $session->id);
        $sales = (clone $orders)->where('type', 'sale');
        $refunds = (clone $orders)->where('type', 'refund');

        $methods = [];
        $rows = PosPayment::query()
            ->join('pos_orders', 'pos_orders.id', '=', 'pos_payments.order_id')
            ->where('pos_payments.session_id', $session->id)
            ->groupBy('pos_payments.method', 'pos_orders.type')
            ->selectRaw('pos_payments.method as method, pos_orders.type as type, SUM(pos_payments.amount) as amount, COUNT(*) as cnt')
            ->get();
        foreach ($rows as $r) {
            $m = $methods[$r->method] ?? ['sales' => 0.0, 'refunds' => 0.0, 'net' => 0.0, 'count' => 0];
            if ($r->type === 'refund') {
                $m['refunds'] += (float) $r->amount;
            } else {
                $m['sales'] += (float) $r->amount;
            }
            $m['count'] += (int) $r->cnt;
            $m['net'] = round($m['sales'] - $m['refunds'], 2);
            $methods[$r->method] = $m;
        }

        $change = (float) (clone $sales)->sum('change_due');
        $cashIn = (float) PosCashMovement::query()->where('session_id', $session->id)->where('type', 'in')->sum('amount');
        $cashOut = (float) PosCashMovement::query()->where('session_id', $session->id)->where('type', 'out')->sum('amount');
        $cashSales = ($methods['cash']['sales'] ?? 0) - $change;
        $cashRefunds = $methods['cash']['refunds'] ?? 0;
        $expected = round((float) $session->opening_cash + $cashSales - $cashRefunds + $cashIn - $cashOut, 2);

        $grossSales = (float) (clone $sales)->sum('subtotal');
        $discounts = (float) (clone $sales)->sum('discount_total');
        $salesTotal = (float) (clone $sales)->sum('total');
        $refundTotal = (float) (clone $refunds)->sum('total');
        $taxSales = (float) (clone $sales)->sum('tax_total');
        $taxRefunds = (float) (clone $refunds)->sum('tax_total');

        return [
            'session_id' => $session->id,
            'register_id' => $session->register_id,
            'z_number' => $session->z_number,
            'opened_at' => optional($session->opened_at)->toIso8601String(),
            'closed_at' => optional($session->closed_at)->toIso8601String(),
            'orders_count' => (clone $sales)->count(),
            'refunds_count' => (clone $refunds)->count(),
            'gross_sales' => round($grossSales, 2),
            'discounts' => round($discounts, 2),
            'sales_total' => round($salesTotal, 2),
            'refunds_total' => round($refundTotal, 2),
            'net_sales' => round($salesTotal - $refundTotal, 2),
            'tax_total' => round($taxSales - $taxRefunds, 2),
            'by_method' => $methods,
            'cash' => [
                'opening' => round((float) $session->opening_cash, 2),
                'sales' => round($cashSales, 2),
                'refunds' => round($cashRefunds, 2),
                'cash_in' => round($cashIn, 2),
                'cash_out' => round($cashOut, 2),
                'expected' => $expected,
                'counted' => $session->counted_cash !== null ? (float) $session->counted_cash : null,
                'difference' => $session->cash_difference !== null ? (float) $session->cash_difference : null,
            ],
        ];
    }

    /** Daily report across every session opened on a date (optionally one register). */
    public function dailyReport(string $date, ?int $registerId): array
    {
        $sessions = PosSession::query()->whereDate('opened_at', $date)
            ->when($registerId, fn ($q) => $q->where('register_id', $registerId))
            ->with(['register:id,name', 'user:id,name'])->orderBy('id')->get();

        $sum = ['orders_count' => 0, 'refunds_count' => 0, 'gross_sales' => 0.0, 'discounts' => 0.0, 'sales_total' => 0.0, 'refunds_total' => 0.0, 'net_sales' => 0.0, 'tax_total' => 0.0, 'cash_difference' => 0.0];
        $methods = [];
        $list = [];
        foreach ($sessions as $s) {
            $r = $this->sessionReport($s);
            foreach (['orders_count', 'refunds_count', 'gross_sales', 'discounts', 'sales_total', 'refunds_total', 'net_sales', 'tax_total'] as $k) {
                $sum[$k] += $r[$k];
            }
            $sum['cash_difference'] += (float) ($s->cash_difference ?? 0);
            foreach ($r['by_method'] as $m => $v) {
                $methods[$m] = round(($methods[$m] ?? 0) + $v['net'], 2);
            }
            $list[] = [
                'id' => $s->id, 'z_number' => $s->z_number, 'status' => $s->status,
                'register' => $s->register?->name, 'user' => $s->user?->name,
                'opened_at' => optional($s->opened_at)->toIso8601String(), 'closed_at' => optional($s->closed_at)->toIso8601String(),
                'net_sales' => $r['net_sales'], 'cash_difference' => $s->cash_difference !== null ? (float) $s->cash_difference : null,
            ];
        }
        foreach ($sum as $k => $v) {
            $sum[$k] = is_float($v) ? round($v, 2) : $v;
        }

        return ['date' => $date, 'register_id' => $registerId, 'summary' => $sum, 'by_method' => $methods, 'sessions' => $list];
    }

    /* ---------------------------------------------------------------- */
    /* Checkout                                                          */
    /* ---------------------------------------------------------------- */

    /**
     * @param  array{lines: list<array{product_id?: int|null, variant_id?: int|null, name?: string|null, quantity: float, unit_price?: float|null, discount_amount?: float|null, discount_percent?: float|null}>, payments: list<array{method: string, amount: float, reference?: string|null}>, customer_id?: int|null, customer_name?: string|null, discount_amount?: float|null, discount_percent?: float|null, notes?: string|null}  $data
     */
    public function checkout(PosSession $session, array $data, int $userId): PosOrder
    {
        $this->assertOpen($session);
        $register = $session->register()->firstOrFail();
        $methods = $register->enabledMethods();

        // Resolve lines against the catalog; free-text lines need a name and a price.
        $resolved = [];
        foreach ($data['lines'] as $i => $l) {
            $qty = (float) $l['quantity'];
            if ($qty <= 0) {
                throw ValidationException::withMessages(["lines.$i.quantity" => __('pos.bad_quantity')]);
            }
            $product = null;
            $variant = null;
            if (! empty($l['variant_id'])) {
                $variant = AccProductVariant::query()->where('is_active', true)->find($l['variant_id']);
                if (! $variant) {
                    throw ValidationException::withMessages(["lines.$i.variant_id" => __('pos.variant_not_found')]);
                }
                $product = AccProduct::query()->find($variant->product_id);
            } elseif (! empty($l['product_id'])) {
                $product = AccProduct::query()->find($l['product_id']);
                if (! $product) {
                    throw ValidationException::withMessages(["lines.$i.product_id" => __('pos.product_not_found')]);
                }
            }
            $catalogPrice = $variant && $variant->sell_price !== null ? (float) $variant->sell_price : ($product ? (float) $product->sell_price : null);
            $price = array_key_exists('unit_price', $l) && $l['unit_price'] !== null ? (float) $l['unit_price'] : $catalogPrice;
            if ($price === null || ($product === null && empty($l['name']))) {
                throw ValidationException::withMessages(["lines.$i" => __('pos.line_incomplete')]);
            }
            $name = $product ? $product->name.($variant ? ' ('.$variant->name.')' : '') : (string) $l['name'];
            $resolved[] = [
                'product' => $product,
                'variant' => $variant,
                'name' => $name,
                'barcode' => $variant?->barcode ?: $product?->barcode,
                'quantity' => $qty,
                'unit_price' => max(0, $price),
                'discount_amount' => (float) ($l['discount_amount'] ?? 0),
                'discount_percent' => (float) ($l['discount_percent'] ?? 0),
            ];
        }

        $calc = PosPricing::calculate($resolved, (float) ($data['discount_amount'] ?? 0), (float) ($data['discount_percent'] ?? 0), (float) $register->tax_rate, (bool) $register->tax_inclusive);
        $total = $calc['total'];

        // Payments: non-cash can never exceed the total; cash may, and the excess is change.
        $byMethod = [];
        $refs = [];
        foreach ($data['payments'] as $i => $p) {
            $m = (string) $p['method'];
            $amt = round((float) $p['amount'], 2);
            if (! in_array($m, $methods, true)) {
                throw ValidationException::withMessages(["payments.$i.method" => __('pos.method_disabled')]);
            }
            if ($amt <= 0) {
                continue;
            }
            $byMethod[$m] = round(($byMethod[$m] ?? 0) + $amt, 2);
            $refs[] = ['method' => $m, 'amount' => $amt, 'reference' => $p['reference'] ?? null];
        }
        $paid = round(array_sum($byMethod), 2);
        $nonCash = round($paid - ($byMethod['cash'] ?? 0), 2);
        if ($nonCash - $total > 0.001) {
            throw ValidationException::withMessages(['payments' => __('pos.non_cash_overpay')]);
        }
        if ($total - $paid > 0.001) {
            throw ValidationException::withMessages(['payments' => __('pos.underpaid')]);
        }
        if (! empty($byMethod['on_account']) && empty($data['customer_id'])) {
            throw ValidationException::withMessages(['customer_id' => __('pos.on_account_needs_customer')]);
        }
        $change = round(max(0, $paid - $total), 2);

        return DB::transaction(function () use ($session, $register, $data, $userId, $resolved, $calc, $total, $byMethod, $refs, $paid, $change) {
            $number = $this->nextNumber($register, 'S');
            $customerName = $data['customer_name'] ?? null;
            if (! empty($data['customer_id']) && ! $customerName) {
                $customerName = DB::table('crm_accounts')->where('id', $data['customer_id'])->value('name');
            }

            $order = PosOrder::query()->create([
                'number' => $number,
                'type' => 'sale',
                'session_id' => $session->id,
                'register_id' => $register->id,
                'user_id' => $userId,
                'company_id' => $register->company_id,
                'branch_id' => $register->branch_id,
                'customer_id' => $data['customer_id'] ?? null,
                'customer_name' => $customerName,
                'status' => 'completed',
                'subtotal' => $calc['subtotal'],
                'discount_total' => $calc['discount_total'],
                'tax_total' => $calc['tax_total'],
                'total' => $total,
                'paid_total' => $paid,
                'change_due' => $change,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($resolved as $i => $r) {
                $c = $calc['lines'][$i];
                $tracked = (bool) ($r['product']?->inventory_controlled) && $register->warehouse_id;
                PosOrderLine::query()->create([
                    'order_id' => $order->id,
                    'product_id' => $r['product']?->id,
                    'variant_id' => $r['variant']?->id,
                    'name' => $r['name'],
                    'barcode' => $r['barcode'],
                    'quantity' => $r['quantity'],
                    'unit_price' => $r['unit_price'],
                    'discount_amount' => $c['discount'],
                    'tax_rate' => (float) $register->tax_rate,
                    'tax_amount' => $c['tax'],
                    'line_total' => $c['total'],
                    'stock_tracked' => $tracked,
                ]);
                if ($tracked) {
                    $this->moveStock($register, (int) $r['product']->id, -$r['quantity'], 'pos_sale', $userId);
                }
            }

            foreach ($refs as $p) {
                PosPayment::query()->create(['order_id' => $order->id, 'session_id' => $session->id] + $p);
            }

            $netByMethod = $byMethod;
            if ($change > 0) {
                $netByMethod['cash'] = round(($netByMethod['cash'] ?? 0) - $change, 2);
            }

            $invoice = $this->createInvoice($order->load('lines'), $netByMethod, $userId);
            $je = $this->accounting->post($order, $netByMethod, $userId);
            $order->update(['sales_invoice_id' => $invoice?->id, 'journal_entry_id' => $je?->id]);

            return $order->fresh(['lines', 'payments']);
        });
    }

    /**
     * @param  list<array{line_id: int, quantity: float}>  $lines
     * @param  list<array{method: string, amount: float, reference?: string|null}>|null  $payments  defaults to cash
     */
    public function refund(PosOrder $original, PosSession $session, array $lines, ?array $payments, bool $restock, ?string $reason, int $userId): PosOrder
    {
        $this->assertOpen($session);
        if ($original->type !== 'sale') {
            throw ValidationException::withMessages(['order' => __('pos.refund_only_sales')]);
        }
        $register = $session->register()->firstOrFail();

        return DB::transaction(function () use ($original, $session, $register, $lines, $payments, $restock, $reason, $userId) {
            PosOrder::query()->whereKey($original->id)->lockForUpdate()->first();
            $origLines = PosOrderLine::query()->where('order_id', $original->id)->get()->keyBy('id');

            $items = [];
            $total = $tax = $subtotal = $discount = 0.0;
            foreach ($lines as $i => $l) {
                $ol = $origLines->get((int) $l['line_id']);
                $qty = (float) $l['quantity'];
                if (! $ol || $qty <= 0) {
                    throw ValidationException::withMessages(["lines.$i" => __('pos.bad_refund_line')]);
                }
                $left = (float) $ol->quantity - (float) $ol->refunded_quantity;
                if ($qty - $left > 0.0005) {
                    throw ValidationException::withMessages(["lines.$i.quantity" => __('pos.refund_exceeds')]);
                }
                $ratio = $qty / (float) $ol->quantity;
                $lt = round((float) $ol->line_total * $ratio, 2);
                $lx = round((float) $ol->tax_amount * $ratio, 2);
                $ld = round((float) $ol->discount_amount * $ratio, 2);
                $items[] = [$ol, $qty, $lt, $lx, $ld];
                $total += $lt;
                $tax += $lx;
                $discount += $ld;
                $subtotal += round((float) $ol->unit_price * $qty, 2);
            }
            $total = round($total, 2);
            if (! $items) {
                throw ValidationException::withMessages(['lines' => __('pos.bad_refund_line')]);
            }

            $payments = $payments ?: [['method' => 'cash', 'amount' => $total]];
            $byMethod = [];
            foreach ($payments as $p) {
                if ((float) $p['amount'] > 0) {
                    $byMethod[$p['method']] = round(($byMethod[$p['method']] ?? 0) + (float) $p['amount'], 2);
                }
            }
            if (abs(array_sum($byMethod) - $total) > 0.01) {
                throw ValidationException::withMessages(['payments' => __('pos.refund_payment_mismatch')]);
            }
            if (! empty($byMethod['on_account']) && ! $original->customer_id) {
                throw ValidationException::withMessages(['payments' => __('pos.on_account_needs_customer')]);
            }
            if (! empty($byMethod['cash']) && $byMethod['cash'] - $this->expectedCash($session) > 0.001) {
                throw ValidationException::withMessages(['payments' => __('pos.not_enough_cash')]);
            }

            $refund = PosOrder::query()->create([
                'number' => $this->nextNumber($register, 'R'),
                'type' => 'refund',
                'session_id' => $session->id,
                'register_id' => $register->id,
                'user_id' => $userId,
                'company_id' => $original->company_id,
                'branch_id' => $original->branch_id,
                'customer_id' => $original->customer_id,
                'customer_name' => $original->customer_name,
                'refund_of_id' => $original->id,
                'status' => 'completed',
                'subtotal' => round($subtotal, 2),
                'discount_total' => round($discount, 2),
                'tax_total' => round($tax, 2),
                'total' => $total,
                'paid_total' => $total,
                'change_due' => 0,
                'notes' => $reason,
            ]);

            foreach ($items as [$ol, $qty, $lt, $lx, $ld]) {
                PosOrderLine::query()->create([
                    'order_id' => $refund->id,
                    'product_id' => $ol->product_id,
                    'variant_id' => $ol->variant_id,
                    'refund_of_line_id' => $ol->id,
                    'name' => $ol->name,
                    'barcode' => $ol->barcode,
                    'quantity' => $qty,
                    'unit_price' => $ol->unit_price,
                    'discount_amount' => $ld,
                    'tax_rate' => $ol->tax_rate,
                    'tax_amount' => $lx,
                    'line_total' => $lt,
                    'stock_tracked' => $restock && $ol->stock_tracked,
                ]);
                $ol->update(['refunded_quantity' => round((float) $ol->refunded_quantity + $qty, 3)]);
                if ($restock && $ol->stock_tracked && $ol->product_id) {
                    $origRegister = PosRegister::query()->find($original->register_id) ?? $register;
                    $this->moveStock($origRegister->warehouse_id ? $origRegister : $register, (int) $ol->product_id, $qty, 'pos_return', $userId);
                }
            }
            foreach ($byMethod as $m => $amt) {
                PosPayment::query()->create(['order_id' => $refund->id, 'session_id' => $session->id, 'method' => $m, 'amount' => $amt]);
            }

            $fully = PosOrderLine::query()->where('order_id', $original->id)->whereColumn('refunded_quantity', '<', 'quantity')->doesntExist();
            $original->update(['status' => $fully ? 'refunded' : 'partially_refunded']);

            $invoice = $this->createInvoice($refund->load('lines'), $byMethod, $userId);
            $je = $this->accounting->post($refund, $byMethod, $userId);
            $refund->update(['sales_invoice_id' => $invoice?->id, 'journal_entry_id' => $je?->id]);

            return $refund->fresh(['lines', 'payments']);
        });
    }

    /* ---------------------------------------------------------------- */
    /* Helpers                                                           */
    /* ---------------------------------------------------------------- */

    public function assertOpen(PosSession $session): void
    {
        if ($session->status !== 'open') {
            throw ValidationException::withMessages(['session' => __('pos.session_closed')]);
        }
    }

    private function moveStock(PosRegister $register, int $productId, float $qty, string $type, int $userId): void
    {
        if (! $register->warehouse_id || ! Schema::hasTable('scm_warehouse_stock')) {
            return;
        }
        if ($qty < 0 && $register->allow_negative_stock) {
            // Allow selling below zero: adjust the stock row directly, still writing the ledger when present.
            $row = DB::table('scm_warehouse_stock')->where('warehouse_id', $register->warehouse_id)->where('product_id', $productId)->lockForUpdate()->first();
            $after = (float) ($row->quantity ?? 0) + $qty;
            DB::table('scm_warehouse_stock')->updateOrInsert(
                ['warehouse_id' => $register->warehouse_id, 'product_id' => $productId],
                ['quantity' => $after, 'updated_at' => now()] + ($row ? [] : ['created_at' => now()])
            );
            if (Schema::hasTable('scm_stock_ledger')) {
                DB::table('scm_stock_ledger')->insert([
                    'warehouse_id' => $register->warehouse_id, 'product_id' => $productId, 'movement_type' => $type,
                    'quantity_change' => $qty, 'quantity_after' => $after, 'unit_cost' => 0, 'created_by' => $userId,
                    'recorded_at' => now(), 'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            return;
        }
        try {
            $this->stock->recordMovement([
                'warehouse_id' => (int) $register->warehouse_id,
                'product_id' => $productId,
                'movement_type' => $type,
                'quantity_change' => $qty,
                'user_id' => $userId,
            ]);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['lines' => __('pos.insufficient_stock').' ('.$e->getMessage().')']);
        }
    }

    private function nextNumber(PosRegister $register, string $kind): string
    {
        PosRegister::query()->whereKey($register->id)->lockForUpdate()->first();
        $prefix = 'POS'.$register->id.'-'.$kind.now()->format('ymd').'-';
        $n = PosOrder::query()->where('number', 'like', $prefix.'%')->count() + 1;
        do {
            $candidate = $prefix.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
            $n++;
        } while (PosOrder::query()->where('number', $candidate)->exists());

        return $candidate;
    }

    /** @param array<string, float> $byMethod */
    private function createInvoice(PosOrder $order, array $byMethod, int $userId): ?SalesInvoice
    {
        if (! Schema::hasTable('sales_invoices')) {
            return null;
        }
        $sign = $order->type === 'refund' ? -1 : 1;
        $method = count($byMethod) === 1 ? array_key_first($byMethod) : 'split';
        $invoice = new SalesInvoice;
        $invoice->forceFill(array_filter([
            'number' => 'INV-'.$order->number,
            'customer_name' => $order->customer_name ?: 'مشتری حضوری',
            'customer_id' => $order->customer_id,
            'subtotal' => $sign * (float) $order->subtotal,
            'discount' => $sign * (float) $order->discount_total,
            'total' => $sign * (float) $order->total,
            'status' => $order->type === 'refund' ? 'refunded' : 'paid',
            'issue_date' => now()->toDateString(),
            'payment_method' => 'pos_'.$method,
            'notes' => $order->type === 'refund' ? 'مرجوعی صندوق برای '.optional($order->refundOf)->number : 'فروش صندوق '.$order->number,
            'created_by' => $userId,
        ], fn ($v) => $v !== null));
        foreach ([
            'tax_amount' => $sign * (float) $order->tax_total,
            'paid_amount' => $sign * (float) $order->total,
            'payment_status' => 'paid',
            'company_id' => $order->company_id,
            'branch_id' => $order->branch_id,
            'due_date' => now()->toDateString(),
        ] as $col => $val) {
            if ($val !== null && Schema::hasColumn('sales_invoices', $col)) {
                $invoice->forceFill([$col => $val]);
            }
        }
        $invoice->save();

        if (Schema::hasTable('sales_invoice_lines')) {
            foreach ($order->lines as $l) {
                SalesInvoiceLine::query()->create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $l->product_id,
                    'item_name' => $l->name,
                    'quantity' => $sign * (float) $l->quantity,
                    'uom' => 'unit',
                    'unit_price' => $l->unit_price,
                    'discount_amount' => $sign * (float) $l->discount_amount,
                    'tax_rate' => $l->tax_rate,
                    'tax_amount' => $sign * (float) $l->tax_amount,
                    'line_total' => $sign * (float) $l->line_total,
                ]);
            }
        }

        return $invoice;
    }
}
