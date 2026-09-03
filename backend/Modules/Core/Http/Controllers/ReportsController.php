<?php

namespace Modules\Core\Http\Controllers;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Crm\Entities\CrmLead;
use Modules\Projects\Entities\Contract;
use Modules\Projects\Entities\PrjSprint;
use Modules\Projects\Entities\PrjTicket;
use Modules\Projects\Entities\Project;
use Modules\Projects\Entities\ProjectTask;
use Modules\Projects\Entities\TimeEntry;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $from = $request->date('from')
            ?? $request->date('date_from')
            ?? now()->startOfMonth();
        $to = $request->date('to')
            ?? $request->date('date_to')
            ?? now();
        $tab = preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $request->input('tab', 'overview'))) ?: 'overview';

        $payload = match ($tab) {
            'sales' => $this->salesTab($from, $to),
            'team' => $this->teamTab($from, $to),
            'customers' => $this->customersTab($from, $to),
            'finance' => $this->financeTab($from, $to),
            'tasks' => $this->tasksTab($from, $to),
            'tickets' => $this->ticketsTab($from, $to),
            'agile' => $this->agileTab($from, $to),
            default => $this->overviewTab($from, $to),
        };

        return response()->json([
            'data' => array_merge([
                'tab' => $tab === '' ? 'overview' : $tab,
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
                'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
                // Flat metrics kept for backward-compatible clients
                'contracts_total' => $this->safeCount(fn () => Contract::query()->whereBetween('created_at', [$from, $to])->count()),
                'tasks_completed' => $this->safeCount(fn () => ProjectTask::query()->where('status', 'done')->whereBetween('updated_at', [$from, $to])->count()),
                'leads_new' => $this->safeCount(fn () => CrmLead::query()->whereBetween('created_at', [$from, $to])->count()),
                'tickets_closed' => $this->safeCount(fn () => PrjTicket::query()->where('status', 'closed')->whereBetween('updated_at', [$from, $to])->count()),
                'sprints_started' => $this->safeCount(fn () => PrjSprint::query()->whereBetween('created_at', [$from, $to])->count()),
                'series' => $this->buildSeries($from, $to),
            ], $payload),
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $from = $request->date('from')
            ?? $request->date('date_from')
            ?? now()->subMonth();
        $to = $request->date('to')
            ?? $request->date('date_to')
            ?? now();
        $tab = preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $request->input('tab', 'overview'))) ?: 'overview';
        $format = strtolower((string) $request->input('format', 'csv'));

        $res = $this->index($request);
        /** @var array<string, mixed> $data */
        $data = $res->getData(true)['data'] ?? [];

        if ($format === 'json') {
            return response()->streamDownload(function () use ($data) {
                echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            }, 'reports-'.$tab.'-'.now()->format('Y-m-d').'.json', [
                'Content-Type' => 'application/json; charset=UTF-8',
            ]);
        }

        $rows = [['metric', 'value']];
        $stats = is_array($data['stats'] ?? null) ? $data['stats'] : [];
        foreach ($stats as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $rows[] = [(string) $key, (string) ($value ?? '')];
            }
        }
        if (count($rows) === 1) {
            $rows[] = ['contracts_total', (string) ($data['contracts_total'] ?? 0)];
            $rows[] = ['tasks_completed', (string) ($data['tasks_completed'] ?? 0)];
            $rows[] = ['leads_new', (string) ($data['leads_new'] ?? 0)];
            $rows[] = ['tickets_closed', (string) ($data['tickets_closed'] ?? 0)];
            $rows[] = ['sprints_started', (string) ($data['sprints_started'] ?? 0)];
        }
        $rows[] = ['from', $from->toDateString()];
        $rows[] = ['to', $to->toDateString()];
        $rows[] = ['tab', $tab];

        $tables = is_array($data['tables'] ?? null) ? $data['tables'] : [];
        foreach ($tables as $tableName => $tableRows) {
            if (! is_array($tableRows) || $tableRows === []) {
                continue;
            }
            $rows[] = [];
            $rows[] = ['table', (string) $tableName];
            $first = $tableRows[0] ?? null;
            if (is_array($first)) {
                $headers = array_keys($first);
                $rows[] = $headers;
                foreach ($tableRows as $tr) {
                    if (! is_array($tr)) {
                        continue;
                    }
                    $rows[] = array_map(static fn ($h) => (string) ($tr[$h] ?? ''), $headers);
                }
            }
        }

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            foreach ($rows as $r) {
                fputcsv($out, $r);
            }
            fclose($out);
        }, 'reports-'.$tab.'-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array{stats: array<string, mixed>, charts: array<string, mixed>, tables: array<string, mixed>}
     */
    private function overviewTab(Carbon $from, Carbon $to): array
    {
        $totalProjects = $this->safeCount(fn () => Project::query()->count());
        $activeProjects = $this->safeCount(fn () => Project::query()->whereIn('status', ['active', 'in_progress', 'open'])->count());
        $totalTasks = $this->safeCount(fn () => ProjectTask::query()->whereBetween('created_at', [$from, $to])->count());
        $completedTasks = $this->safeCount(fn () => ProjectTask::query()->where('status', 'done')->whereBetween('updated_at', [$from, $to])->count());
        $totalLeads = $this->safeCount(fn () => CrmLead::query()->whereBetween('created_at', [$from, $to])->count());
        $converted = $this->safeCount(fn () => CrmLead::query()->whereNotNull('converted_at')->whereBetween('converted_at', [$from, $to])->count());
        $totalRevenue = $this->safeSum(fn () => (float) Contract::query()->whereBetween('created_at', [$from, $to])->sum('amount'));
        $totalTickets = $this->safeCount(fn () => PrjTicket::query()->whereBetween('created_at', [$from, $to])->count());

        $series = $this->buildSeries($from, $to);
        $daily = [];
        $days = collect($series['contracts'] ?? [])->pluck('day')->all();
        foreach ($days as $i => $day) {
            $daily[] = [
                'date' => $day,
                'contracts' => (int) ($series['contracts'][$i]['count'] ?? 0),
                'tasks' => (int) ($series['tasks_completed'][$i]['count'] ?? 0),
                'tickets' => (int) ($series['tickets_closed'][$i]['count'] ?? 0),
                'projects' => (int) ($series['leads'][$i]['count'] ?? 0),
                'total' => (int) (
                    ($series['contracts'][$i]['count'] ?? 0)
                    + ($series['tasks_completed'][$i]['count'] ?? 0)
                    + ($series['tickets_closed'][$i]['count'] ?? 0)
                    + ($series['leads'][$i]['count'] ?? 0)
                ),
            ];
        }

        return [
            'stats' => [
                'total_projects' => $totalProjects,
                'active_projects' => $activeProjects,
                'total_tasks' => $totalTasks,
                'completed_tasks' => $completedTasks,
                'task_completion_rate' => $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0,
                'total_leads' => $totalLeads,
                'conversion_rate' => $totalLeads > 0 ? round(($converted / $totalLeads) * 100, 1) : 0,
                'total_revenue' => $totalRevenue,
                'total_tickets' => $totalTickets,
                'total_contracts' => $this->safeCount(fn () => Contract::query()->whereBetween('created_at', [$from, $to])->count()),
            ],
            'charts' => [
                'daily' => $daily,
                'monthly' => $this->monthlyPerformance(6),
                'status_distribution' => $this->taskStatusDistribution($from, $to),
            ],
            'tables' => [
                'leads_by_status' => $this->leadsByStatus($from, $to),
            ],
        ];
    }

    /**
     * @return array{stats: array<string, mixed>, charts: array<string, mixed>, tables: array<string, mixed>}
     */
    private function salesTab(Carbon $from, Carbon $to): array
    {
        $byMonth = [];
        if (Schema::hasTable('prj_contracts')) {
            $contracts = Contract::query()
                ->whereBetween('created_at', [$from, $to])
                ->get(['created_at', 'amount']);
            $grouped = [];
            foreach ($contracts as $c) {
                $month = $c->created_at?->format('Y-m') ?? '';
                if ($month === '') {
                    continue;
                }
                if (! isset($grouped[$month])) {
                    $grouped[$month] = ['month' => $month, 'count' => 0, 'total' => 0.0];
                }
                $grouped[$month]['count']++;
                $grouped[$month]['total'] += (float) ($c->amount ?? 0);
            }
            ksort($grouped);
            $byMonth = array_values($grouped);
        }

        $topCustomers = [];
        if (Schema::hasTable('prj_contracts')) {
            $topCustomers = Contract::query()
                ->whereBetween('created_at', [$from, $to])
                ->selectRaw('COALESCE(customer_account_id, owner_user_id, 0) as customer_key, COUNT(*) as contract_count, COALESCE(SUM(amount),0) as total_value')
                ->groupBy('customer_key')
                ->orderByDesc('total_value')
                ->limit(10)
                ->get()
                ->map(function ($r) {
                    $name = 'Customer #'.$r->customer_key;
                    if ($r->customer_key && Schema::hasTable('users')) {
                        $u = User::query()->find($r->customer_key);
                        if ($u) {
                            $name = $u->name;
                        }
                    }

                    return [
                        'customer_name' => $name,
                        'contract_count' => (int) $r->contract_count,
                        'total_value' => (float) $r->total_value,
                    ];
                })->all();
        }

        return [
            'stats' => [
                'total_contracts' => $this->safeCount(fn () => Contract::query()->whereBetween('created_at', [$from, $to])->count()),
                'total_revenue' => $this->safeSum(fn () => (float) Contract::query()->whereBetween('created_at', [$from, $to])->sum('amount')),
                'total_leads' => $this->safeCount(fn () => CrmLead::query()->whereBetween('created_at', [$from, $to])->count()),
            ],
            'charts' => [
                'monthly' => array_map(fn ($r) => [
                    'month' => $r['month'],
                    'total' => $r['total'],
                    'contracts' => $r['count'],
                ], $byMonth),
            ],
            'tables' => [
                'sales_by_month' => $byMonth,
                'top_customers' => $topCustomers,
            ],
        ];
    }

    /**
     * @return array{stats: array<string, mixed>, charts: array<string, mixed>, tables: array<string, mixed>}
     */
    private function teamTab(Carbon $from, Carbon $to): array
    {
        $tasksByMember = [];
        if (Schema::hasTable('prj_tasks')) {
            $tasksByMember = DB::table('prj_tasks')
                ->leftJoin('users', 'users.id', '=', 'prj_tasks.assignee_id')
                ->whereBetween('prj_tasks.created_at', [$from, $to])
                ->selectRaw("COALESCE(users.name, ?) as user_name, COUNT(*) as total_tasks, SUM(CASE WHEN prj_tasks.status = 'done' THEN 1 ELSE 0 END) as completed_tasks", ['Unassigned'])
                ->groupBy('user_name')
                ->orderByDesc('total_tasks')
                ->limit(20)
                ->get()
                ->map(fn ($r) => [
                    'user_name' => (string) $r->user_name,
                    'total_tasks' => (int) $r->total_tasks,
                    'completed_tasks' => (int) $r->completed_tasks,
                    'activity_score' => (int) $r->total_tasks + ((int) $r->completed_tasks * 2),
                ])->all();
        }

        return [
            'stats' => [
                'total_tasks' => $this->safeCount(fn () => ProjectTask::query()->whereBetween('created_at', [$from, $to])->count()),
                'completed_tasks' => $this->safeCount(fn () => ProjectTask::query()->where('status', 'done')->whereBetween('updated_at', [$from, $to])->count()),
            ],
            'charts' => [],
            'tables' => [
                'tasks_by_member' => $tasksByMember,
                'time_by_member' => $this->timeByMember($from, $to),
            ],
        ];
    }

    /**
     * @return array{stats: array<string, mixed>, charts: array<string, mixed>, tables: array<string, mixed>}
     */
    private function customersTab(Carbon $from, Carbon $to): array
    {
        $newCustomers = $this->safeCount(fn () => CrmLead::query()->whereNotNull('converted_at')->whereBetween('converted_at', [$from, $to])->count());
        $totalLeads = $this->safeCount(fn () => CrmLead::query()->whereBetween('created_at', [$from, $to])->count());
        $revenue = $this->safeSum(fn () => (float) Contract::query()->whereBetween('created_at', [$from, $to])->sum('amount'));
        $contracts = $this->safeCount(fn () => Contract::query()->whereBetween('created_at', [$from, $to])->count());

        $ltv = [];
        if (Schema::hasTable('prj_contracts')) {
            $ltv = Contract::query()
                ->whereBetween('created_at', [$from, $to])
                ->selectRaw('COALESCE(customer_account_id, owner_user_id, created_by, 0) as customer_key, COUNT(*) as total_contracts, COALESCE(SUM(amount),0) as lifetime_value')
                ->groupBy('customer_key')
                ->orderByDesc('lifetime_value')
                ->limit(15)
                ->get()
                ->map(function ($r) {
                    $name = 'Customer #'.$r->customer_key;
                    if ($r->customer_key) {
                        $u = User::query()->find($r->customer_key);
                        if ($u) {
                            $name = $u->name;
                        }
                    }

                    return [
                        'customer_name' => $name,
                        'total_contracts' => (int) $r->total_contracts,
                        'lifetime_value' => (float) $r->lifetime_value,
                    ];
                })->all();
        }

        return [
            'stats' => [
                'new_customers' => $newCustomers,
                'retention_rate' => $totalLeads > 0 ? round(($newCustomers / $totalLeads) * 100, 1) : 0,
                'avg_customer_value' => $contracts > 0 ? round($revenue / $contracts, 2) : 0,
            ],
            'charts' => [],
            'tables' => [
                'customer_ltv' => $ltv,
            ],
        ];
    }

    /**
     * @return array{stats: array<string, mixed>, charts: array<string, mixed>, tables: array<string, mixed>}
     */
    private function financeTab(Carbon $from, Carbon $to): array
    {
        return [
            'stats' => [
                'total_revenue' => $this->safeSum(fn () => (float) Contract::query()->whereBetween('created_at', [$from, $to])->sum('amount')),
                'total_contracts' => $this->safeCount(fn () => Contract::query()->whereBetween('created_at', [$from, $to])->count()),
            ],
            'charts' => [
                'monthly' => $this->monthlyPerformance(6),
            ],
            'tables' => [],
        ];
    }

    /**
     * @return array{stats: array<string, mixed>, charts: array<string, mixed>, tables: array<string, mixed>}
     */
    private function tasksTab(Carbon $from, Carbon $to): array
    {
        $total = $this->safeCount(fn () => ProjectTask::query()->whereBetween('created_at', [$from, $to])->count());
        $completed = $this->safeCount(fn () => ProjectTask::query()->where('status', 'done')->whereBetween('updated_at', [$from, $to])->count());
        $timeRows = $this->timeByMember($from, $to);
        $minutes = array_sum(array_map(fn ($r) => (float) ($r['total_minutes'] ?? 0), $timeRows));

        return [
            'stats' => [
                'total_tasks' => $total,
                'completed_tasks' => $completed,
                'task_completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
                'total_time_hours' => round($minutes / 60, 2),
            ],
            'charts' => [
                'status_distribution' => $this->taskStatusDistribution($from, $to),
            ],
            'tables' => [
                'time_by_member' => $timeRows,
            ],
        ];
    }

    /**
     * @return array{stats: array<string, mixed>, charts: array<string, mixed>, tables: array<string, mixed>}
     */
    private function ticketsTab(Carbon $from, Carbon $to): array
    {
        $total = $this->safeCount(fn () => PrjTicket::query()->whereBetween('created_at', [$from, $to])->count());
        $closed = $this->safeCount(fn () => PrjTicket::query()->where('status', 'closed')->whereBetween('updated_at', [$from, $to])->count());

        return [
            'stats' => [
                'total_tickets' => $total,
                'tickets_closed' => $closed,
                'avg_response_time' => '—',
            ],
            'charts' => [],
            'tables' => [],
        ];
    }

    /**
     * @return array{stats: array<string, mixed>, charts: array<string, mixed>, tables: array<string, mixed>}
     */
    private function agileTab(Carbon $from, Carbon $to): array
    {
        $totalProjects = $this->safeCount(fn () => Project::query()->count());
        $activeProjects = $this->safeCount(fn () => Project::query()->whereIn('status', ['active', 'in_progress', 'open'])->count());
        $totalTasks = $this->safeCount(fn () => ProjectTask::query()->whereBetween('created_at', [$from, $to])->count());
        $completed = $this->safeCount(fn () => ProjectTask::query()->where('status', 'done')->whereBetween('updated_at', [$from, $to])->count());

        return [
            'stats' => [
                'total_projects' => $totalProjects,
                'active_projects' => $activeProjects,
                'task_completion_rate' => $totalTasks > 0 ? round(($completed / $totalTasks) * 100, 1) : 0,
                'sprints_started' => $this->safeCount(fn () => PrjSprint::query()->whereBetween('created_at', [$from, $to])->count()),
            ],
            'charts' => [
                'monthly' => $this->monthlyPerformance(6),
            ],
            'tables' => [],
        ];
    }

    /**
     * @return list<array{user_name: string, total_minutes: float, billable_minutes: float, total_revenue: float, entry_count: int}>
     */
    private function timeByMember(Carbon $from, Carbon $to): array
    {
        if (! Schema::hasTable('prj_time_entries')) {
            return [];
        }

        return TimeEntry::query()
            ->leftJoin('users', 'users.id', '=', 'prj_time_entries.user_id')
            ->whereBetween('prj_time_entries.started_at', [$from, $to])
            ->selectRaw('COALESCE(users.name, ?) as user_name, COALESCE(SUM(duration_seconds),0)/60 as total_minutes, COALESCE(SUM(CASE WHEN is_billable = 1 THEN duration_seconds ELSE 0 END),0)/60 as billable_minutes, COUNT(*) as entry_count', ['Unknown'])
            ->groupBy('user_name')
            ->orderByDesc('total_minutes')
            ->limit(20)
            ->get()
            ->map(fn ($r) => [
                'user_name' => (string) $r->user_name,
                'total_minutes' => round((float) $r->total_minutes, 1),
                'billable_minutes' => round((float) $r->billable_minutes, 1),
                'total_revenue' => 0,
                'entry_count' => (int) $r->entry_count,
            ])->all();
    }

    /**
     * @return list<array{label: string, count: int}>
     */
    private function taskStatusDistribution(Carbon $from, Carbon $to): array
    {
        try {
            return ProjectTask::query()
                ->whereBetween('created_at', [$from, $to])
                ->selectRaw('status as label, COUNT(*) as count')
                ->groupBy('status')
                ->get()
                ->map(fn ($r) => ['label' => (string) $r->label, 'count' => (int) $r->count])
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return list<array{status: string, count: int}>
     */
    private function leadsByStatus(Carbon $from, Carbon $to): array
    {
        try {
            if (! Schema::hasTable('crm_statuses')) {
                return CrmLead::query()
                    ->whereBetween('created_at', [$from, $to])
                    ->selectRaw('status_id as status, COUNT(*) as count')
                    ->groupBy('status_id')
                    ->get()
                    ->map(fn ($r) => ['status' => (string) $r->status, 'count' => (int) $r->count])
                    ->all();
            }

            return CrmLead::query()
                ->leftJoin('crm_statuses', 'crm_statuses.id', '=', 'crm_leads.status_id')
                ->whereBetween('crm_leads.created_at', [$from, $to])
                ->selectRaw('COALESCE(crm_statuses.name, ?) as status, COUNT(*) as count', ['Unknown'])
                ->groupBy('status')
                ->get()
                ->map(fn ($r) => ['status' => (string) $r->status, 'count' => (int) $r->count])
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return list<array{month: string, projects: int, tasks: int, tickets: int, contracts: int, total: float|int}>
     */
    private function monthlyPerformance(int $months): array
    {
        $out = [];
        $cursor = now()->startOfMonth()->subMonths($months - 1);
        for ($i = 0; $i < $months; $i++) {
            $start = $cursor->copy();
            $end = $cursor->copy()->endOfMonth();
            $contracts = $this->safeCount(fn () => Contract::query()->whereBetween('created_at', [$start, $end])->count());
            $tasks = $this->safeCount(fn () => ProjectTask::query()->where('status', 'done')->whereBetween('updated_at', [$start, $end])->count());
            $tickets = $this->safeCount(fn () => PrjTicket::query()->where('status', 'closed')->whereBetween('updated_at', [$start, $end])->count());
            $projects = $this->safeCount(fn () => Project::query()->whereBetween('created_at', [$start, $end])->count());
            $revenue = $this->safeSum(fn () => (float) Contract::query()->whereBetween('created_at', [$start, $end])->sum('amount'));
            $out[] = [
                'month' => $start->format('Y-m'),
                'projects' => $projects,
                'tasks' => $tasks,
                'tickets' => $tickets,
                'contracts' => $contracts,
                'total' => $revenue > 0 ? $revenue : ($contracts + $tasks + $tickets + $projects),
            ];
            $cursor->addMonth();
        }

        return $out;
    }

    /**
     * @return array<string, list<array{day: string, count: int}>>
     */
    private function buildSeries(Carbon $from, Carbon $to): array
    {
        $fromDay = $from->copy()->startOfDay();
        $toDay = $to->copy()->endOfDay();

        $mapSeries = static function ($plucked) use ($fromDay, $toDay): array {
            $out = [];
            $cursor = $fromDay->copy();
            while ($cursor->lte($toDay)) {
                $day = $cursor->toDateString();
                $out[] = ['day' => $day, 'count' => (int) ($plucked[$day] ?? 0)];
                $cursor->addDay();
            }

            return $out;
        };

        try {
            $contracts = Contract::query()
                ->whereBetween('created_at', [$fromDay, $toDay])
                ->selectRaw('DATE(created_at) as day, COUNT(*) as count')
                ->groupBy('day')
                ->pluck('count', 'day');
            $tasks = ProjectTask::query()
                ->where('status', 'done')
                ->whereBetween('updated_at', [$fromDay, $toDay])
                ->selectRaw('DATE(updated_at) as day, COUNT(*) as count')
                ->groupBy('day')
                ->pluck('count', 'day');
            $leads = CrmLead::query()
                ->whereBetween('created_at', [$fromDay, $toDay])
                ->selectRaw('DATE(created_at) as day, COUNT(*) as count')
                ->groupBy('day')
                ->pluck('count', 'day');
            $tickets = PrjTicket::query()
                ->where('status', 'closed')
                ->whereBetween('updated_at', [$fromDay, $toDay])
                ->selectRaw('DATE(updated_at) as day, COUNT(*) as count')
                ->groupBy('day')
                ->pluck('count', 'day');
            $sprints = PrjSprint::query()
                ->whereBetween('created_at', [$fromDay, $toDay])
                ->selectRaw('DATE(created_at) as day, COUNT(*) as count')
                ->groupBy('day')
                ->pluck('count', 'day');
        } catch (\Throwable) {
            return [
                'contracts' => $mapSeries(collect()),
                'tasks_completed' => $mapSeries(collect()),
                'leads' => $mapSeries(collect()),
                'tickets_closed' => $mapSeries(collect()),
                'sprints' => $mapSeries(collect()),
            ];
        }

        return [
            'contracts' => $mapSeries($contracts),
            'tasks_completed' => $mapSeries($tasks),
            'leads' => $mapSeries($leads),
            'tickets_closed' => $mapSeries($tickets),
            'sprints' => $mapSeries($sprints),
        ];
    }

    private function safeCount(callable $fn): int
    {
        try {
            return (int) $fn();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function safeSum(callable $fn): float
    {
        try {
            return (float) $fn();
        } catch (\Throwable) {
            return 0.0;
        }
    }
}
