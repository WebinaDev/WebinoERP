<?php

namespace Modules\Crm\Services\Customer360;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Crm\Entities\CrmAccount;
use Modules\Crm\Services\Hub\CustomerHubService;

/**
 * Customer BI reports (PHASE5_API.md §4). Revenue is based on sales_invoices linked by customer_id
 * (the same source as the hub financials); dates filter on issue_date.
 */
class CustomerBiService
{
    public const SECTIONS = ['ltv', 'debt', 'churn', 'cohorts', 'top', 'segments'];

    private const CHURNED_STATUS = ['churned', 'suspended', 'inactive'];

    public function __construct(private CustomerHubService $hub) {}

    /** @param array{from?:?string,to?:?string,segment?:?string,limit?:int} $f */
    public function report(string $section, array $f): array
    {
        return match ($section) {
            'ltv' => $this->ltv($f), 'debt' => $this->debt($f), 'churn' => $this->churn($f),
            'cohorts' => $this->cohorts($f), 'top' => $this->top($f), 'segments' => $this->segments($f),
        };
    }

    private function accounts(array $f): Collection
    {
        return DB::table('crm_accounts')->whereNull('merged_into_id')
            ->when(Schema::hasColumn('crm_accounts', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
            ->when(! empty($f['segment']), fn ($q) => $q->where('segment', $f['segment']))
            ->get(['id', 'name', 'segment', 'hub_status', 'created_at', 'payment_terms_days'])->keyBy('id');
    }

    private function invoices(array $f, Collection $accounts): Collection
    {
        if (! Schema::hasColumn('sales_invoices', 'customer_id')) {
            return collect();
        }

        return DB::table('sales_invoices')->whereIn('customer_id', $accounts->keys())
            ->when(! empty($f['from']), fn ($q) => $q->whereDate('issue_date', '>=', $f['from']))
            ->when(! empty($f['to']), fn ($q) => $q->whereDate('issue_date', '<=', $f['to']))
            ->get(['id', 'customer_id', 'status', 'total', 'issue_date', 'updated_at']);
    }

    private function paid(Collection $inv): Collection
    {
        return $inv->filter(fn ($i) => in_array($i->status, CustomerHubService::PAID_INVOICE, true));
    }

    private function open(Collection $inv): Collection
    {
        return $inv->reject(fn ($i) => in_array($i->status, CustomerHubService::CLOSED_INVOICE, true));
    }

    private function ltv(array $f): array
    {
        $acc = $this->accounts($f);
        $rev = $this->paid($this->invoices($f, $acc))->groupBy('customer_id')->map(fn ($g) => (float) $g->sum('total'));
        $values = $rev->values()->sort()->values();
        $n = $values->count();
        $median = $n === 0 ? 0 : ($n % 2 ? $values[intdiv($n, 2)] : ($values[$n / 2 - 1] + $values[$n / 2]) / 2);
        $edges = [[0, 10_000_000], [10_000_000, 50_000_000], [50_000_000, 200_000_000], [200_000_000, 1_000_000_000], [1_000_000_000, null]];

        return [
            'avg_ltv' => $n ? round($values->avg(), 2) : 0, 'median_ltv' => round((float) $median, 2),
            'total_revenue' => round((float) $values->sum(), 2), 'customers' => $n,
            'buckets' => array_map(fn ($e) => [
                'label' => number_format($e[0]).($e[1] ? '–'.number_format($e[1]) : '+'), 'from' => $e[0], 'to' => $e[1],
                'count' => $values->filter(fn ($v) => $v >= $e[0] && ($e[1] === null || $v < $e[1]))->count(),
            ], $edges),
        ];
    }

    private function debt(array $f): array
    {
        $acc = $this->accounts($f);
        $open = $this->open($this->invoices($f, $acc));
        $b = ['current' => 0.0, 'd1_30' => 0.0, 'd31_60' => 0.0, 'd61_90' => 0.0, 'd90_plus' => 0.0];
        $per = [];
        $today = CarbonImmutable::today();
        foreach ($open as $i) {
            $issued = $i->issue_date ? CarbonImmutable::parse($i->issue_date) : $today;
            $due = $issued->addDays((int) ($acc[$i->customer_id]->payment_terms_days ?? 30));
            $late = $due->lt($today) ? (int) $due->diffInDays($today) : 0;
            $key = match (true) { $late === 0 => 'current', $late <= 30 => 'd1_30', $late <= 60 => 'd31_60', $late <= 90 => 'd61_90', default => 'd90_plus' };
            $b[$key] += (float) $i->total;
            $p = &$per[$i->customer_id];
            $p['outstanding'] = ($p['outstanding'] ?? 0) + (float) $i->total;
            $p['overdue'] = ($p['overdue'] ?? 0) + ($late > 0 ? (float) $i->total : 0);
            $p['oldest_days'] = max($p['oldest_days'] ?? 0, (int) $issued->diffInDays($today));
            unset($p);
        }
        $top = collect($per)->map(fn ($p, $id) => ['id' => (int) $id, 'name' => $acc[$id]->name, 'outstanding' => round($p['outstanding'], 2), 'overdue' => round($p['overdue'], 2), 'oldest_days' => $p['oldest_days']])
            ->sortByDesc('outstanding')->take((int) ($f['limit'] ?? 20))->values()->all();

        return ['total' => round(array_sum($b), 2), 'buckets' => array_map(fn ($v) => round($v, 2), $b), 'top_debtors' => $top];
    }

    private function churn(array $f): array
    {
        $acc = $this->accounts($f);
        $lastPaid = $this->paid($this->invoices([], $acc))->groupBy('customer_id')->map(fn ($g) => $g->max(fn ($i) => $i->issue_date ?: $i->updated_at));
        $cut = now()->subDays(180);
        $churned = $acc->filter(fn ($a) => in_array($a->hub_status, self::CHURNED_STATUS, true)
            || (! isset($lastPaid[$a->id]) ? CarbonImmutable::parse($a->created_at ?? now())->lt($cut) : CarbonImmutable::parse($lastPaid[$a->id])->lt($cut)));
        $active = $acc->diffKeys($churned);
        $risk = $active->take(500)->map(function ($a) {
            $h = $this->hub->health(CrmAccount::query()->find($a->id));

            return ['id' => (int) $a->id, 'name' => $a->name, 'health' => (int) $h['score']];
        })->filter(fn ($r) => $r['health'] < 40)->sortBy('health')->values();
        $months = collect(range(11, 0))->map(fn ($m) => now()->startOfMonth()->subMonths($m)->format('Y-m'));

        return [
            'active' => $active->count(), 'churned' => $churned->count(), 'at_risk' => $risk->count(),
            'churn_rate' => $acc->count() ? round($churned->count() / $acc->count() * 100, 1) : 0,
            'by_month' => $months->map(fn ($m) => [
                'month' => $m,
                'churned' => $churned->filter(fn ($a) => isset($lastPaid[$a->id]) && CarbonImmutable::parse($lastPaid[$a->id])->addDays(180)->format('Y-m') === $m)->count(),
                'new' => $acc->filter(fn ($a) => $a->created_at && CarbonImmutable::parse($a->created_at)->format('Y-m') === $m)->count(),
            ])->values()->all(),
            'at_risk_list' => $risk->take((int) ($f['limit'] ?? 20))->all(),
        ];
    }

    private function cohorts(array $f): array
    {
        $acc = $this->accounts($f);
        $paid = $this->paid($this->invoices([], $acc))->filter(fn ($i) => $i->issue_date);
        $months = $paid->groupBy('customer_id')->map(fn ($g) => $g->map(fn ($i) => CarbonImmutable::parse($i->issue_date)->format('Y-m'))->unique()->sort()->values());
        $out = [];
        foreach ($months->groupBy(fn ($m) => $m->first()) as $cohort => $members) {
            if ((! empty($f['from']) && $cohort < substr($f['from'], 0, 7)) || (! empty($f['to']) && $cohort > substr($f['to'], 0, 7))) {
                continue;
            }
            $start = CarbonImmutable::parse($cohort.'-01');
            $size = $members->count();
            $out[] = ['cohort' => $cohort, 'size' => $size, 'months' => array_map(fn ($k) => round($members->filter(fn ($m) => $m->contains($start->addMonths($k)->format('Y-m')))->count() / $size * 100, 1), range(0, 11))];
        }
        usort($out, fn ($a, $b) => strcmp($a['cohort'], $b['cohort']));

        return $out;
    }

    private function top(array $f): array
    {
        $acc = $this->accounts($f);

        return $this->paid($this->invoices($f, $acc))->groupBy('customer_id')->map(fn ($g, $id) => [
            'id' => (int) $id, 'name' => $acc[$id]->name, 'revenue' => round((float) $g->sum('total'), 2), 'invoices' => $g->count(),
            'last_paid_at' => ($d = $g->max(fn ($i) => $i->issue_date ?: $i->updated_at)) ? CarbonImmutable::parse($d)->toIso8601String() : null,
        ])->sortByDesc('revenue')->take(max(1, min(200, (int) ($f['limit'] ?? 20))))->values()->all();
    }

    private function segments(array $f): array
    {
        $acc = $this->accounts($f);
        $inv = $this->invoices($f, $acc);
        $rev = $this->paid($inv)->groupBy('customer_id')->map(fn ($g) => (float) $g->sum('total'));
        $out = $this->open($inv)->groupBy('customer_id')->map(fn ($g) => (float) $g->sum('total'));

        return $acc->groupBy(fn ($a) => $a->segment ?: 'none')->map(function ($g, $seg) use ($rev, $out) {
            $r = $g->sum(fn ($a) => $rev[$a->id] ?? 0);

            return ['segment' => $seg, 'customers' => $g->count(), 'revenue' => round($r, 2), 'avg_ltv' => round($r / max(1, $g->count()), 2), 'outstanding' => round($g->sum(fn ($a) => $out[$a->id] ?? 0), 2)];
        })->sortByDesc('revenue')->values()->all();
    }
}
