<?php

namespace Modules\Crm\Http\Controllers\Customer360;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Crm\Entities\CrmAccount;
use Modules\Crm\Services\Hub\CustomerHubService as S;
use Modules\Crm\Services\Hub\CustomerResolver;

/** /crm/customers/{id}/hub/accounting[/ledger|/invoices|/payments] (PHASE3_API.md §2). */
class HubAccountingController extends Hub360Base
{
    public const TERM_DAYS = 30;

    public function summary(Request $r, int $id): JsonResponse
    {
        $a = $this->account($r, $id, 'accounting');
        $mo = $this->money($r);
        $f = $this->hub->financials($a);
        $inv = $this->invoiceRows($a);
        $paid = $this->hub->payments($a)->where('paid', true);
        $aging = ['current' => 0.0, 'd1_30' => 0.0, 'd31_60' => 0.0, 'd61_90' => 0.0, 'd90_plus' => 0.0];
        foreach ($inv->where('open', true) as $i) {
            $d = $i['overdue_days'];
            $k = $d <= 0 ? 'current' : ($d <= 30 ? 'd1_30' : ($d <= 60 ? 'd31_60' : ($d <= 90 ? 'd61_90' : 'd90_plus')));
            $aging[$k] += (float) $i['remaining'];
        }
        $overdue = $inv->where('open', true)->where('overdue_days', '>', 0);
        $person = $this->person($a);
        $last = $paid->first();

        return response()->json(['data' => [
            'currency' => 'IRR',
            'balance' => $this->m($mo, $f['balance']), 'outstanding' => $this->m($mo, $f['outstanding']),
            'overdue' => $this->m($mo, $overdue->sum('remaining')), 'overdue_count' => $overdue->count(),
            'total_invoiced' => $this->m($mo, $inv->sum('total')), 'total_paid' => $this->m($mo, $f['total_paid']),
            'wallet_credit' => $this->m($mo, $f['wallet_credit']), 'credit_limit' => $this->m($mo, $f['credit_limit']),
            'aging' => $mo ? array_map(fn ($v) => round($v, 2), $aging) : null,
            'last_payment' => $last ? $this->payRow($last, $mo) : null,
            'acc_person' => $person ? ['id' => $person->id, 'name' => $person->name] : null,
        ], 'meta' => ['can' => ['record_payment' => $this->access->canManage($r->user(), 'payment'), 'create_invoice' => $this->access->canManage($r->user(), 'invoice')]]]);
    }

    public function ledger(Request $r, int $id): JsonResponse
    {
        $a = $this->account($r, $id, 'accounting');
        $r->validate(['from' => 'nullable|date', 'to' => 'nullable|date', 'per_page' => 'nullable|integer|min:1|max:100', 'page' => 'nullable|integer|min:1']);
        $mo = $this->money($r);
        $e = collect();
        foreach ($this->hub->invoices($a) as $i) {
            if (in_array($i->status, ['draft', 'cancelled', 'canceled', 'void'], true)) {
                continue;
            }
            $e->push(['date' => (string) ($i->issue_date ?? $i->created_at), 'kind' => 'invoice', 'ref' => 'inv-'.$i->id,
                'description' => 'فاکتور '.($i->invoice_number ?? $i->number), 'debit' => (float) $i->total, 'credit' => null]);
        }
        foreach ($this->hub->payments($a)->where('paid', true) as $p) {
            $e->push(['date' => (string) $p['date'], 'kind' => 'payment', 'ref' => $p['id'],
                'description' => 'پرداخت '.($p['gateway'] ?? ''), 'debit' => null, 'credit' => (float) $p['amount']]);
        }
        if (($person = $this->person($a)) && Schema::hasTable('acc_receipt_vouchers')) {
            foreach (DB::table('acc_receipt_vouchers')->where('person_id', $person->id)->get() as $v) {
                $refund = $v->type === 'payment';
                $e->push(['date' => (string) $v->document_date, 'kind' => $refund ? 'refund' : 'receipt', 'ref' => 'rv-'.$v->id,
                    'description' => $v->description ?: ($refund ? 'پرداخت به مشتری' : 'رسید دریافت').' '.$v->number,
                    'debit' => $refund ? (float) $v->amount : null, 'credit' => $refund ? null : (float) $v->amount]);
            }
        }
        $e = $e->sortBy(fn ($x) => substr($x['date'], 0, 19).$x['ref'])->values();
        $bal = 0.0;
        $opening = 0.0;
        $from = $r->query('from') ? CarbonImmutable::parse($r->query('from'))->startOfDay() : null;
        $to = $r->query('to') ? CarbonImmutable::parse($r->query('to'))->endOfDay() : null;
        $rows = collect();
        $closing = 0.0;
        foreach ($e as $x) {
            $bal += (float) $x['credit'] - (float) $x['debit'];
            $d = CarbonImmutable::parse($x['date']);
            if ($from && $d->lt($from)) {
                $opening = $closing = $bal;

                continue;
            }
            if ($to && $d->gt($to)) {
                continue;
            }
            $closing = $bal;
            $rows->push(['date' => $this->iso($x['date']), 'kind' => $x['kind'], 'ref' => $x['ref'], 'description' => trim($x['description']),
                'debit' => $this->m($mo, $x['debit']), 'credit' => $this->m($mo, $x['credit']), 'balance' => $this->m($mo, $bal)]);
        }
        [$data, $meta] = $this->page($r, $rows);
        $meta['opening_balance'] = $this->m($mo, $opening);
        $meta['closing_balance'] = $this->m($mo, $closing);

        return response()->json(['data' => $data, 'meta' => $meta]);
    }

    public function invoices(Request $r, int $id): JsonResponse
    {
        $a = $this->account($r, $id, 'accounting');
        $r->validate(['status' => 'nullable|in:open,paid,overdue,all']);
        $mo = $this->money($r);
        $all = $this->invoiceRows($a);
        $st = $r->query('status', 'all');
        $rows = $all->filter(fn ($i) => match ($st) {
            'open' => $i['open'], 'paid' => in_array($i['status'], S::PAID_INVOICE, true),
            'overdue' => $i['open'] && $i['overdue_days'] > 0, default => true,
        })->map(function ($i) use ($mo) {
            foreach (['total', 'paid_amount', 'remaining'] as $k) {
                $i[$k] = $this->m($mo, $i[$k]);
            }
            unset($i['open']);

            return $i;
        })->values();
        [$data, $meta] = $this->page($r, $rows);
        $meta['kpis'] = ['open' => $all->where('open', true)->count(), 'overdue' => $all->where('open', true)->where('overdue_days', '>', 0)->count(),
            'paid' => $all->filter(fn ($i) => in_array($i['status'], S::PAID_INVOICE, true))->count()];

        return response()->json(['data' => $data, 'meta' => $meta]);
    }

    public function payments(Request $r, int $id): JsonResponse
    {
        $a = $this->account($r, $id, 'accounting');
        $mo = $this->money($r);
        [$data, $meta] = $this->page($r, $this->hub->payments($a)->map(fn ($p) => $this->payRow($p, $mo)));

        return response()->json(['data' => $data, 'meta' => $meta]);
    }

    private function payRow(array $p, bool $mo): array
    {
        return ['id' => $p['id'], 'source' => $p['source'], 'date' => $this->iso($p['date']), 'amount' => $this->m($mo, $p['amount']),
            'gateway' => $p['gateway'] ?? null, 'tracking_code' => $p['tracking_code'] ?? null, 'status' => $p['status'], 'paid' => (bool) $p['paid'],
            'invoice_id' => $p['invoice_id'] ?? null];
    }

    /** Sales invoices with remaining / overdue_days (due = due_date or issue_date + 30d). */
    public function invoiceRows(CrmAccount $a): Collection
    {
        $paidBy = $this->hub->payments($a)->where('paid', true)->whereNotNull('invoice_id')->groupBy('invoice_id')->map(fn ($g) => (float) $g->sum('amount'));

        return $this->hub->invoices($a)->map(function ($i) use ($paidBy) {
            $isPaid = in_array($i->status, S::PAID_INVOICE, true);
            $open = ! in_array($i->status, S::CLOSED_INVOICE, true);
            $paidAmt = $isPaid ? (float) $i->total : min((float) $i->total, (float) ($paidBy[$i->id] ?? 0));
            $due = ($i->due_date ?? null) ? CarbonImmutable::parse($i->due_date)
                : ($i->issue_date ? CarbonImmutable::parse($i->issue_date)->addDays(self::TERM_DAYS) : null);
            $od = $open && $due && $due->lt(now()->startOfDay()) ? (int) $due->diffInDays(now()->startOfDay()) : 0;

            return ['id' => $i->id, 'number' => $i->invoice_number ?? $i->number, 'status' => $i->status, 'issue_date' => $i->issue_date,
                'due_date' => $due?->toDateString(), 'total' => (float) $i->total, 'paid_amount' => $paidAmt,
                'remaining' => $open ? max(0, (float) $i->total - $paidAmt) : 0.0, 'overdue_days' => $od, 'source' => 'sales',
                'project_id' => $i->project_id ?? null, 'open' => $open];
        })->values();
    }

    public function person(CrmAccount $a): ?object
    {
        if (! Schema::hasTable('acc_persons')) {
            return null;
        }
        $q = DB::table('acc_persons');
        $nid = trim((string) ($a->national_id ?? ''));
        $eco = trim((string) ($a->economic_code ?? ''));
        $mob = CustomerResolver::normMobile($a->mobile ?? null);
        if ($nid === '' && $eco === '' && ! $mob) {
            return null;
        }

        return $q->where(function ($w) use ($nid, $eco, $mob) {
            $nid !== '' && $w->orWhere('national_id', $nid);
            $eco !== '' && $w->orWhere('economic_code', $eco);
            $mob && $w->orWhereIn('mobile', array_unique([$mob, '0'.ltrim($mob, '0'), '+98'.ltrim($mob, '0')]));
        })->orderBy('id')->first();
    }
}
