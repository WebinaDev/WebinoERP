<?php

namespace Modules\Core\Http\Controllers;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Services\StaffOpsService;
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
        $status = $this->sanitizeFilter($request->input('status'));
        $site = $this->sanitizeFilter($request->input('site'));

        $ops = app(StaffOpsService::class);
        $payload = match ($tab) {
            'sales' => $this->salesTab($from, $to, $status),
            'team' => $this->teamTab($from, $to, $status),
            'customers' => $this->customersTab($from, $to),
            'finance' => $this->mergeReport($this->financeTab($from, $to, $status), $ops->report('finance', $from, $to, $status)),
            'tasks' => $this->tasksTab($from, $to, $status),
            'tickets' => $this->ticketsTab($from, $to, $status),
            'agile' => $this->agileTab($from, $to, $status),
            'sites' => $ops->report('sites', $from, $to, $status, $site),
            'marketing' => $ops->report('marketing', $from, $to, $status),
            'notifications' => $ops->report('notifications', $from, $to, $status),
            default => $this->mergeReport($this->overviewTab($from, $to), $ops->report('overview', $from, $to)),
        };

        return response()->json([
            'data' => array_merge([
                'tab' => $tab === '' ? 'overview' : $tab,
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
                'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
                'filters' => [
                    'status' => $status !== '' ? $status : null,
                    'site' => $site !== '' ? $site : null,
                ],
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
    private function salesTab(Carbon $from, Carbon $to, string $status = ''): array
    {
        $byMonth = [];
        $contractsQuery = Schema::hasTable('prj_contracts')
            ? Contract::query()->whereBetween('created_at', [$from, $to])
            : null;
        if ($contractsQuery && $status !== '' && Schema::hasColumn('prj_contracts', 'status')) {
            $contractsQuery->where('status', $status);
        }

        if ($contractsQuery) {
            $contracts = (clone $contractsQuery)->get(['created_at', 'amount', 'status']);
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
        if ($contractsQuery) {
            $topCustomers = (clone $contractsQuery)
                ->selectRaw('COALESCE(customer_account_id, customer_user_id, 0) as customer_key, COUNT(*) as contract_count, COALESCE(SUM(amount),0) as total_value')
                ->groupBy('customer_key')
                ->orderByDesc('total_value')
                ->limit(10)
                ->get()
                ->map(function ($r) {
                    $name = '__customer__:'.$r->customer_key;
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

        $totalContracts = $this->safeCount(fn () => $contractsQuery ? (clone $contractsQuery)->count() : 0);
        $totalRevenue = $this->safeSum(fn () => $contractsQuery ? (float) (clone $contractsQuery)->sum('amount') : 0.0);
        $totalLeads = $this->safeCount(fn () => CrmLead::query()->whereBetween('created_at', [$from, $to])->count());
        $converted = $this->safeCount(fn () => CrmLead::query()->whereNotNull('converted_at')->whereBetween('converted_at', [$from, $to])->count());

        return [
            'stats' => [
                'total_contracts' => $totalContracts,
                'total_revenue' => $totalRevenue,
                'avg_deal_size' => $totalContracts > 0 ? round($totalRevenue / $totalContracts, 2) : 0,
                'total_leads' => $totalLeads,
                'conversion_rate' => $totalLeads > 0 ? round(($converted / $totalLeads) * 100, 1) : 0,
            ],
            'charts' => [
                'monthly' => array_map(fn ($r) => [
                    'month' => $r['month'],
                    'total' => $r['total'],
                    'contracts' => $r['count'],
                ], $byMonth),
                'status_distribution' => $this->contractStatusDistribution($from, $to),
            ],
            'tables' => [
                'sales_by_month' => $byMonth,
                'top_customers' => $topCustomers,
                'contracts_by_status' => $this->contractStatusDistribution($from, $to),
            ],
            'filter_options' => [
                'statuses' => $this->distinctStatuses('prj_contracts'),
            ],
        ];
    }

    /**
     * @return array{stats: array<string, mixed>, charts: array<string, mixed>, tables: array<string, mixed>}
     */
    private function teamTab(Carbon $from, Carbon $to, string $status = ''): array
    {
        $tasksByMember = [];
        if (Schema::hasTable('prj_tasks')) {
            $q = DB::table('prj_tasks')
                ->leftJoin('users', 'users.id', '=', 'prj_tasks.assignee_id')
                ->whereBetween('prj_tasks.created_at', [$from, $to]);
            if ($status !== '') {
                $q->where('prj_tasks.status', $status);
            }
            $tasksByMember = $q
                ->selectRaw("COALESCE(users.name, ?) as user_name, COUNT(*) as total_tasks, SUM(CASE WHEN prj_tasks.status IN ('done','completed','closed') THEN 1 ELSE 0 END) as completed_tasks", ['__unassigned__'])
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

        $total = $this->safeCount(fn () => ProjectTask::query()->whereBetween('created_at', [$from, $to])->when($status !== '', fn ($q) => $q->where('status', $status))->count());
        $completed = $this->safeCount(fn () => ProjectTask::query()->whereIn('status', ['done', 'completed', 'closed'])->whereBetween('updated_at', [$from, $to])->count());
        $timeRows = $this->timeByMember($from, $to);
        $minutes = array_sum(array_map(fn ($r) => (float) ($r['total_minutes'] ?? 0), $timeRows));

        return [
            'stats' => [
                'total_tasks' => $total,
                'completed_tasks' => $completed,
                'task_completion_rate' => $total > 0 ? round(($completed / max($total, 1)) * 100, 1) : 0,
                'active_members' => count($tasksByMember),
                'total_time_hours' => round($minutes / 60, 2),
            ],
            'charts' => [
                'status_distribution' => $this->taskStatusDistribution($from, $to),
                'member_activity' => array_map(fn ($r) => [
                    'label' => $r['user_name'],
                    'count' => $r['total_tasks'],
                ], array_slice($tasksByMember, 0, 10)),
            ],
            'tables' => [
                'tasks_by_member' => $tasksByMember,
                'time_by_member' => $timeRows,
            ],
            'filter_options' => [
                'statuses' => $this->distinctStatuses('prj_tasks'),
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
        $uniqueCustomers = $this->safeCount(function () use ($from, $to) {
            if (! Schema::hasTable('prj_contracts')) {
                return 0;
            }

            return (int) Contract::query()
                ->whereBetween('created_at', [$from, $to])
                ->selectRaw('COUNT(DISTINCT COALESCE(customer_account_id, customer_user_id, created_by, 0)) as c')
                ->value('c');
        });

        $ltv = [];
        if (Schema::hasTable('prj_contracts')) {
            $ltv = Contract::query()
                ->whereBetween('created_at', [$from, $to])
                ->selectRaw('COALESCE(customer_account_id, customer_user_id, created_by, 0) as customer_key, COUNT(*) as total_contracts, COALESCE(SUM(amount),0) as lifetime_value')
                ->groupBy('customer_key')
                ->orderByDesc('lifetime_value')
                ->limit(15)
                ->get()
                ->map(function ($r) {
                    $name = '__customer__:'.$r->customer_key;
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

        $monthlyNew = [];
        try {
            $cursor = $from->copy()->startOfMonth();
            $end = $to->copy()->endOfMonth();
            while ($cursor->lte($end)) {
                $start = $cursor->copy()->startOfMonth();
                $monthEnd = $cursor->copy()->endOfMonth();
                $monthlyNew[] = [
                    'month' => $start->format('Y-m'),
                    'total' => $this->safeCount(fn () => CrmLead::query()->whereNotNull('converted_at')->whereBetween('converted_at', [$start, $monthEnd])->count()),
                    'contracts' => $this->safeCount(fn () => Contract::query()->whereBetween('created_at', [$start, $monthEnd])->count()),
                ];
                $cursor->addMonth();
            }
        } catch (\Throwable) {
            $monthlyNew = [];
        }

        return [
            'stats' => [
                'new_customers' => $newCustomers,
                'unique_customers' => $uniqueCustomers,
                'total_leads' => $totalLeads,
                'retention_rate' => $totalLeads > 0 ? round(($newCustomers / $totalLeads) * 100, 1) : 0,
                'avg_customer_value' => $contracts > 0 ? round($revenue / $contracts, 2) : 0,
                'total_revenue' => $revenue,
            ],
            'charts' => [
                'monthly' => $monthlyNew,
            ],
            'tables' => [
                'customer_ltv' => $ltv,
                'leads_by_status' => $this->leadsByStatus($from, $to),
            ],
        ];
    }

    /**
     * @return array{stats: array<string, mixed>, charts: array<string, mixed>, tables: array<string, mixed>}
     */
    private function financeTab(Carbon $from, Carbon $to, string $status = ''): array
    {
        $revenue = $this->safeSum(fn () => (float) Contract::query()->whereBetween('created_at', [$from, $to])->when(
            $status !== '' && Schema::hasColumn('prj_contracts', 'status'),
            fn ($q) => $q->where('status', $status)
        )->sum('amount'));
        $contracts = $this->safeCount(fn () => Contract::query()->whereBetween('created_at', [$from, $to])->when(
            $status !== '' && Schema::hasColumn('prj_contracts', 'status'),
            fn ($q) => $q->where('status', $status)
        )->count());

        return [
            'stats' => [
                'total_revenue' => $revenue,
                'total_contracts' => $contracts,
                'avg_contract_value' => $contracts > 0 ? round($revenue / $contracts, 2) : 0,
            ],
            'charts' => [
                'monthly' => $this->monthlyPerformance(6),
                'status_distribution' => $this->contractStatusDistribution($from, $to),
            ],
            'tables' => [
                'contracts_by_status' => $this->contractStatusDistribution($from, $to),
            ],
            'filter_options' => [
                'statuses' => array_values(array_unique(array_merge(
                    $this->distinctStatuses('prj_contracts'),
                    $this->distinctStatuses('acc_invoices'),
                    $this->distinctStatuses('sales_invoices'),
                ))),
            ],
        ];
    }

    /**
     * @return array{stats: array<string, mixed>, charts: array<string, mixed>, tables: array<string, mixed>}
     */
    private function tasksTab(Carbon $from, Carbon $to, string $status = ''): array
    {
        $base = ProjectTask::query()->whereBetween('created_at', [$from, $to]);
        if ($status !== '') {
            $base->where('status', $status);
        }
        $total = $this->safeCount(fn () => (clone $base)->count());
        $completed = $this->safeCount(fn () => ProjectTask::query()->whereIn('status', ['done', 'completed', 'closed'])->whereBetween('updated_at', [$from, $to])->count());
        $overdue = $this->safeCount(fn () => ProjectTask::query()
            ->whereNotIn('status', ['done', 'completed', 'closed', 'cancelled'])
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->count());
        $open = $this->safeCount(fn () => ProjectTask::query()->whereNotIn('status', ['done', 'completed', 'closed', 'cancelled'])->count());
        $timeRows = $this->timeByMember($from, $to);
        $minutes = array_sum(array_map(fn ($r) => (float) ($r['total_minutes'] ?? 0), $timeRows));
        $statusDist = $this->taskStatusDistribution($from, $to);

        return [
            'stats' => [
                'total_tasks' => $total,
                'completed_tasks' => $completed,
                'open_tasks' => $open,
                'overdue_tasks' => $overdue,
                'task_completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
                'total_time_hours' => round($minutes / 60, 2),
            ],
            'charts' => [
                'status_distribution' => $statusDist,
                'daily' => $this->dailyTaskSeries($from, $to),
            ],
            'tables' => [
                'time_by_member' => $timeRows,
                'tasks_by_status' => array_map(fn ($r) => [
                    'status' => $r['label'],
                    'count' => $r['count'],
                ], $statusDist),
            ],
            'filter_options' => [
                'statuses' => $this->distinctStatuses('prj_tasks'),
            ],
        ];
    }

    /**
     * @return array{stats: array<string, mixed>, charts: array<string, mixed>, tables: array<string, mixed>}
     */
    private function ticketsTab(Carbon $from, Carbon $to, string $status = ''): array
    {
        $base = PrjTicket::query()->whereBetween('created_at', [$from, $to]);
        if ($status !== '') {
            $base->where('status', $status);
        }
        $total = $this->safeCount(fn () => (clone $base)->count());
        $closed = $this->safeCount(fn () => PrjTicket::query()->whereIn('status', ['closed', 'resolved'])->whereBetween('updated_at', [$from, $to])->count());
        $open = $this->safeCount(fn () => PrjTicket::query()->whereIn('status', ['open', 'pending', 'in_progress'])->count());
        $slaBreaches = $this->safeCount(function () use ($from, $to) {
            if (! Schema::hasColumn('prj_tickets', 'sla_breached_at')) {
                return 0;
            }

            return PrjTicket::query()->whereBetween('created_at', [$from, $to])->whereNotNull('sla_breached_at')->count();
        });

        $byStatus = $this->ticketGroupCounts($from, $to, 'status');
        $byPriority = $this->ticketGroupCounts($from, $to, 'priority');
        $byAssignee = [];
        if (Schema::hasTable('prj_tickets')) {
            $byAssignee = DB::table('prj_tickets')
                ->leftJoin('users', 'users.id', '=', 'prj_tickets.assignee_id')
                ->whereBetween('prj_tickets.created_at', [$from, $to])
                ->when($status !== '', fn ($q) => $q->where('prj_tickets.status', $status))
                ->selectRaw('COALESCE(users.name, ?) as user_name, COUNT(*) as count', ['__unassigned__'])
                ->groupBy('user_name')
                ->orderByDesc('count')
                ->limit(15)
                ->get()
                ->map(fn ($r) => [
                    'user_name' => (string) $r->user_name,
                    'count' => (int) $r->count,
                ])->all();
        }

        $recent = [];
        try {
            $recent = (clone $base)
                ->orderByDesc('id')
                ->limit(15)
                ->get(['id', 'subject', 'status', 'priority', 'created_at', 'first_responded_at'])
                ->map(fn (PrjTicket $t) => [
                    'id' => (int) $t->id,
                    'title' => (string) ($t->subject ?? ''),
                    'status' => (string) $t->status,
                    'priority' => (string) ($t->priority ?? ''),
                    'created_at' => optional($t->created_at)?->utc()->toIso8601String(),
                    'first_responded_at' => optional($t->first_responded_at)?->utc()->toIso8601String(),
                ])->all();
        } catch (\Throwable) {
            $recent = [];
        }

        return [
            'stats' => [
                'total_tickets' => $total,
                'tickets_closed' => $closed,
                'open_tickets' => $open,
                'sla_breaches' => $slaBreaches,
                'avg_response_time' => $this->averageResponseHours($from, $to),
                'close_rate' => $total > 0 ? round(($closed / $total) * 100, 1) : 0,
            ],
            'charts' => [
                'status_distribution' => array_map(fn ($r) => [
                    'label' => $r['status'],
                    'count' => $r['count'],
                ], $byStatus),
                'priority_distribution' => array_map(fn ($r) => [
                    'label' => $r['priority'] !== '' ? $r['priority'] : '__unknown__',
                    'count' => $r['count'],
                ], $byPriority),
                'daily' => $this->dailyTicketSeries($from, $to),
            ],
            'tables' => [
                'tickets_by_status' => $byStatus,
                'tickets_by_priority' => $byPriority,
                'tickets_by_assignee' => $byAssignee,
                'recent_tickets' => $recent,
            ],
            'filter_options' => [
                'statuses' => $this->distinctStatuses('prj_tickets'),
            ],
        ];
    }

    /**
     * @return array{stats: array<string, mixed>, charts: array<string, mixed>, tables: array<string, mixed>}
     */
    private function agileTab(Carbon $from, Carbon $to, string $status = ''): array
    {
        $projectsQ = Project::query();
        if ($status !== '' && Schema::hasColumn('prj_projects', 'status')) {
            $projectsQ->where('status', $status);
        }
        $totalProjects = $this->safeCount(fn () => (clone $projectsQ)->count());
        $activeProjects = $this->safeCount(fn () => Project::query()->whereIn('status', ['active', 'in_progress', 'open'])->count());
        $totalTasks = $this->safeCount(fn () => ProjectTask::query()->whereBetween('created_at', [$from, $to])->count());
        $completed = $this->safeCount(fn () => ProjectTask::query()->whereIn('status', ['done', 'completed', 'closed'])->whereBetween('updated_at', [$from, $to])->count());
        $sprintsStarted = $this->safeCount(fn () => PrjSprint::query()->whereBetween('created_at', [$from, $to])->count());
        $sprintsActive = $this->safeCount(fn () => Schema::hasColumn('prj_sprints', 'status')
            ? PrjSprint::query()->whereIn('status', ['active', 'in_progress', 'running'])->count()
            : 0);

        $projectsByStatus = [];
        try {
            $projectsByStatus = Project::query()
                ->selectRaw('COALESCE(status, ?) as status, COUNT(*) as count', ['__unknown__'])
                ->groupBy('status')
                ->orderByDesc('count')
                ->get()
                ->map(fn ($r) => ['status' => (string) $r->status, 'count' => (int) $r->count])
                ->all();
        } catch (\Throwable) {
            $projectsByStatus = [];
        }

        $sprints = [];
        try {
            if (Schema::hasTable('prj_sprints')) {
                $sprints = PrjSprint::query()
                    ->whereBetween('created_at', [$from, $to])
                    ->orderByDesc('id')
                    ->limit(20)
                    ->get(['id', 'name', 'status', 'starts_at', 'ends_at', 'created_at'])
                    ->map(fn (PrjSprint $s) => [
                        'id' => (int) $s->id,
                        'title' => (string) ($s->name ?? ''),
                        'status' => (string) ($s->status ?? ''),
                        'starts_at' => optional($s->starts_at)?->toDateString(),
                        'ends_at' => optional($s->ends_at)?->toDateString(),
                        'created_at' => optional($s->created_at)?->utc()->toIso8601String(),
                    ])->all();
            }
        } catch (\Throwable) {
            $sprints = [];
        }

        return [
            'stats' => [
                'total_projects' => $totalProjects,
                'active_projects' => $activeProjects,
                'task_completion_rate' => $totalTasks > 0 ? round(($completed / $totalTasks) * 100, 1) : 0,
                'sprints_started' => $sprintsStarted,
                'sprints_active' => $sprintsActive,
                'completed_tasks' => $completed,
            ],
            'charts' => [
                'monthly' => $this->monthlyPerformance(6),
                'status_distribution' => array_map(fn ($r) => [
                    'label' => $r['status'],
                    'count' => $r['count'],
                ], $projectsByStatus),
            ],
            'tables' => [
                'projects_by_status' => $projectsByStatus,
                'recent_sprints' => $sprints,
            ],
            'filter_options' => [
                'statuses' => $this->distinctStatuses('prj_projects'),
            ],
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
            ->selectRaw('COALESCE(users.name, ?) as user_name, COALESCE(SUM(duration_seconds),0)/60 as total_minutes, COALESCE(SUM(CASE WHEN is_billable = true THEN duration_seconds ELSE 0 END),0)/60 as billable_minutes, COUNT(*) as entry_count', ['__unknown__'])
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
                ->selectRaw('COALESCE(crm_statuses.name, ?) as status, COUNT(*) as count', ['__unknown__'])
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

    /**
     * @param  array{stats?: array<string, mixed>, charts?: array<string, mixed>, tables?: array<string, mixed>}  $base
     * @param  array{stats?: array<string, mixed>, charts?: array<string, mixed>, tables?: array<string, mixed>}  $extra
     * @return array{stats: array<string, mixed>, charts: array<string, mixed>, tables: array<string, mixed>}
     */
    private function mergeReport(array $base, array $extra): array
    {
        $out = [
            'stats' => array_merge($base['stats'] ?? [], $extra['stats'] ?? []),
            'charts' => array_merge($base['charts'] ?? [], $extra['charts'] ?? []),
            'tables' => array_merge($base['tables'] ?? [], $extra['tables'] ?? []),
        ];
        $filters = array_merge($base['filter_options'] ?? [], $extra['filter_options'] ?? []);
        if ($filters !== []) {
            $out['filter_options'] = $filters;
        }

        return $out;
    }

    private function averageResponseHours(Carbon $from, Carbon $to): float
    {
        if (! Schema::hasColumn('prj_tickets', 'first_responded_at')) {
            return 0;
        }

        try {
            $rows = PrjTicket::query()
                ->whereBetween('created_at', [$from, $to])
                ->whereNotNull('first_responded_at')
                ->get(['created_at', 'first_responded_at']);
            if ($rows->isEmpty()) {
                return 0;
            }
            $minutes = $rows->avg(function (PrjTicket $ticket) {
                return $ticket->created_at && $ticket->first_responded_at
                    ? $ticket->created_at->diffInMinutes($ticket->first_responded_at)
                    : 0;
            });

            return round(((float) $minutes) / 60, 1);
        } catch (\Throwable) {
            return 0;
        }
    }

    private function sanitizeFilter(mixed $value): string
    {
        $raw = trim((string) $value);
        if ($raw === '' || $raw === 'all' || $raw === '*') {
            return '';
        }

        return preg_replace('/[^a-zA-Z0-9_\-\.\s\x{0600}-\x{06FF}]/u', '', $raw) ?? '';
    }

    /**
     * @return list<string>
     */
    private function distinctStatuses(string $table): array
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'status')) {
            return [];
        }

        try {
            return DB::table($table)
                ->whereNotNull('status')
                ->where('status', '!=', '')
                ->distinct()
                ->orderBy('status')
                ->limit(40)
                ->pluck('status')
                ->map(fn ($s) => (string) $s)
                ->values()
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return list<array{status: string, count: int}|array{priority: string, count: int}>
     */
    private function ticketGroupCounts(Carbon $from, Carbon $to, string $column): array
    {
        $column = in_array($column, ['status', 'priority'], true) ? $column : 'status';
        if (! Schema::hasTable('prj_tickets') || ! Schema::hasColumn('prj_tickets', $column)) {
            return [];
        }

        try {
            return PrjTicket::query()
                ->whereBetween('created_at', [$from, $to])
                ->selectRaw("COALESCE({$column}, ?) as grp, COUNT(*) as count", ['__unknown__'])
                ->groupBy('grp')
                ->orderByDesc('count')
                ->get()
                ->map(function ($r) use ($column) {
                    return [
                        $column => (string) $r->grp,
                        'count' => (int) $r->count,
                    ];
                })
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return list<array{label: string, count: int}|array{status: string, count: int}>
     */
    private function contractStatusDistribution(Carbon $from, Carbon $to): array
    {
        if (! Schema::hasTable('prj_contracts') || ! Schema::hasColumn('prj_contracts', 'status')) {
            return [];
        }

        try {
            return Contract::query()
                ->whereBetween('created_at', [$from, $to])
                ->selectRaw('COALESCE(status, ?) as status, COUNT(*) as count', ['__unknown__'])
                ->groupBy('status')
                ->orderByDesc('count')
                ->get()
                ->map(fn ($r) => [
                    'status' => (string) $r->status,
                    'label' => (string) $r->status,
                    'count' => (int) $r->count,
                ])
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return list<array{date: string, total: int}>
     */
    private function dailyTaskSeries(Carbon $from, Carbon $to): array
    {
        return $this->dailySeriesFor('prj_tasks', $from, $to);
    }

    /**
     * @return list<array{date: string, total: int}>
     */
    private function dailyTicketSeries(Carbon $from, Carbon $to): array
    {
        return $this->dailySeriesFor('prj_tickets', $from, $to);
    }

    /**
     * @return list<array{date: string, total: int}>
     */
    private function dailySeriesFor(string $table, Carbon $from, Carbon $to): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }

        try {
            $plucked = DB::table($table)
                ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
                ->selectRaw('DATE(created_at) as day, COUNT(*) as count')
                ->groupBy('day')
                ->pluck('count', 'day');
            $out = [];
            $cursor = $from->copy()->startOfDay();
            $end = $to->copy()->endOfDay();
            while ($cursor->lte($end)) {
                $day = $cursor->toDateString();
                $out[] = ['date' => $day, 'total' => (int) ($plucked[$day] ?? 0)];
                $cursor->addDay();
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
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
