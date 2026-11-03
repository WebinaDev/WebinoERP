<?php

namespace Modules\Core\Http\Controllers;

use App\Support\CustomerAccess;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Accounting\Entities\AccountingInvoice;
use Modules\Core\Entities\DashboardWidgetPref;
use Modules\Core\Services\DashboardAccess;
use Modules\Core\Services\StaffOpsService;
use Modules\Crm\Entities\CrmAccount;
use Modules\Crm\Entities\CrmActivity;
use Modules\Crm\Entities\CrmConsultation;
use Modules\Crm\Entities\CrmDeal;
use Modules\Crm\Entities\CrmLead;
use Modules\Crm\Entities\CrmPipeline;
use Modules\Crm\Services\CrmForecastService;
use Modules\Projects\Entities\Contract;
use Modules\Projects\Entities\PrjTicket;
use Modules\Projects\Entities\Project;
use Modules\Projects\Entities\ProjectTask;
use Modules\Sales\Entities\SalesInvoice;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;

class DashboardParityController extends Controller
{
    public function __construct(private readonly DashboardAccess $access) {}

    public function full(Request $request): JsonResponse
    {
        $user = $request->user();
        $role = $this->access->roleFor($user);
        $prefs = DashboardWidgetPref::query()
            ->where('user_id', $user?->id ?? 0)
            ->get()
            ->keyBy('widget_key');

        $catalog = [
            'recent_leads' => [
                'title' => 'آخرین سرنخ‌ها',
                'href' => 'crm/leads',
                'items' => fn () => $this->mapLeads(),
            ],
            'recent_deals' => [
                'title' => 'آخرین معاملات',
                'href' => 'crm/deals',
                'items' => fn () => $this->mapDeals(),
            ],
            'recent_tasks' => [
                'title' => 'آخرین وظایف',
                'href' => 'pm/tasks',
                'items' => fn () => $this->mapTasks($user),
            ],
            'recent_tickets' => [
                'title' => 'آخرین تیکت‌ها',
                'href' => 'crm/tickets',
                'items' => fn () => $this->mapTickets($user),
            ],
            'recent_projects' => [
                'title' => 'آخرین پروژه‌ها',
                'href' => 'pm/projects',
                'items' => fn () => $this->mapProjects($user),
            ],
            'recent_invoices' => [
                'title' => 'آخرین فاکتورها',
                'href' => 'finance/invoices',
                'items' => fn () => $this->mapInvoices(),
            ],
            'recent_sites' => [
                'title' => 'آخرین سایت‌ها',
                'href' => 'admin/platform/sites',
                'items' => fn () => $this->mapSites(),
            ],
        ];

        $widgets = [];
        foreach ($catalog as $id => $meta) {
            if (! $this->access->canListWidget($role, $id)) {
                continue;
            }
            $pref = $prefs->get($id);
            if ($pref && ! $pref->is_visible) {
                continue;
            }
            $widgets[] = [
                'id' => $id,
                'title' => $meta['title'],
                'type' => 'list',
                'href' => $meta['href'],
                'sort_order' => $pref ? (int) $pref->sort_order : 0,
                'items' => $this->safeList($meta['items']),
            ];
        }

        usort($widgets, static fn ($a, $b) => ($a['sort_order'] <=> $b['sort_order']) ?: strcmp($a['id'], $b['id']));

        return response()->json([
            'data' => [
                'role' => $role,
                'sections' => $this->access->allowedSections($role),
                'widgets' => array_map(static function (array $w) {
                    unset($w['sort_order']);

                    return $w;
                }, $widgets),
                'stats' => $this->roleStats($role, $user),
            ],
        ]);
    }

    /**
     * @return array<string, int|float>
     */
    private function roleStats(string $role, mixed $user): array
    {
        $customer = app(CustomerAccess::class);

        if ($role === DashboardAccess::ROLE_CLIENT) {
            $projects = Project::query();
            $tickets = PrjTicket::query()->whereIn('status', ['open', 'pending', 'in_progress']);
            if ($user) {
                $customer->scopeProjects($projects, $user);
                $customer->scopeTickets($tickets, $user);
            }

            return [
                'leads' => 0,
                'projects' => $this->safeCount(fn () => $projects->count()),
                'tasks_open' => 0,
                'tickets_open' => $this->safeCount(fn () => $tickets->count()),
                'contracts' => 0,
            ];
        }

        if ($role === DashboardAccess::ROLE_TEAM_MEMBER) {
            $uid = $user?->id;

            return [
                'leads' => 0,
                'projects' => $this->safeCount(fn () => Project::query()
                    ->where(function ($q) use ($uid) {
                        $q->where('manager_user_id', $uid)
                            ->orWhereHas('tasks', fn ($t) => $t->where('assignee_id', $uid));
                    })
                    ->count()),
                'tasks_open' => $this->safeCount(fn () => ProjectTask::query()->where('assignee_id', $uid)->whereNotIn('status', ['done', 'completed', 'cancelled', 'closed'])->count()),
                'tickets_open' => $this->safeCount(fn () => PrjTicket::query()->where('assignee_id', $uid)->whereIn('status', ['open', 'pending', 'in_progress'])->count()),
                'contracts' => 0,
            ];
        }

        return [
            'leads' => $this->safeCount(fn () => CrmLead::query()->count()),
            'projects' => $this->safeCount(fn () => Project::query()->count()),
            'tasks_open' => $this->safeCount(fn () => ProjectTask::query()->whereNotIn('status', ['done', 'completed', 'cancelled', 'closed'])->count()),
            'tickets_open' => $this->safeCount(fn () => PrjTicket::query()->whereIn('status', ['open', 'pending', 'in_progress'])->count()),
            'contracts' => $this->safeCount(fn () => Contract::query()->count()),
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

    /**
     * @return list<array<string, mixed>>
     */
    private function safeList(callable $fn): array
    {
        try {
            $result = $fn();

            return is_array($result) ? $result : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mapLeads(): array
    {
        return CrmLead::query()->orderByDesc('id')->limit(8)->get()->map(function (CrmLead $row) {
            $name = trim(($row->first_name ?? '').' '.($row->last_name ?? ''));
            if ($name === '') {
                $name = (string) ($row->email ?? $row->company ?? '#'.$row->id);
            }

            return [
                'id' => $row->id,
                'title' => $name,
                'status' => (string) ($row->status_id ?? 'new'),
                'subtitle' => (string) ($row->email ?? $row->phone ?? ''),
                'href' => 'crm/leads',
            ];
        })->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mapDeals(): array
    {
        return CrmDeal::query()->orderByDesc('id')->limit(8)->get()->map(function (CrmDeal $row) {
            $amount = (float) ($row->amount ?? 0);
            $currency = (string) ($row->currency_code ?: Money::displayCurrency());
            $status = $row->won_at ? 'won' : ($row->lost_at ? 'lost' : 'open');

            return [
                'id' => $row->id,
                'title' => (string) ($row->name ?: '#'.$row->id),
                'status' => $status,
                'amount' => $amount,
                'amount_display' => $this->formatMoney($amount, $currency),
                'subtitle' => $this->formatMoney($amount, $currency),
                'href' => 'crm/deals',
            ];
        })->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mapTasks(mixed $user): array
    {
        $q = ProjectTask::query()->orderByDesc('id')->limit(8);
        if ($user && $this->access->roleFor($user) === DashboardAccess::ROLE_TEAM_MEMBER) {
            $q->where('assignee_id', $user->id);
        }

        return $q->get()->map(fn (ProjectTask $row) => [
            'id' => $row->id,
            'title' => (string) ($row->title ?: '#'.$row->id),
            'status' => (string) ($row->status ?? 'open'),
            'subtitle' => optional($row->due_at)?->toDateString(),
            'due_at' => optional($row->due_at)?->utc()->toIso8601String(),
            'href' => 'pm/tasks',
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mapTickets(mixed $user): array
    {
        $q = PrjTicket::query()->orderByDesc('id')->limit(8);
        $role = $this->access->roleFor($user);
        if ($user) {
            $customer = app(CustomerAccess::class);
            if ($role === DashboardAccess::ROLE_CLIENT) {
                $customer->scopeTickets($q, $user);
            } elseif ($role === DashboardAccess::ROLE_TEAM_MEMBER) {
                $q->where('assignee_id', $user->id);
            }
        }

        return $q->get()->map(fn (PrjTicket $row) => [
            'id' => $row->id,
            'title' => (string) ($row->subject ?: '#'.$row->id),
            'status' => (string) ($row->status ?? 'open'),
            'subtitle' => (string) ($row->priority ?? ''),
            'href' => 'crm/tickets',
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mapProjects(mixed $user): array
    {
        $q = Project::query()->orderByDesc('id')->limit(8);
        $role = $this->access->roleFor($user);
        if ($user) {
            $customer = app(CustomerAccess::class);
            if ($role === DashboardAccess::ROLE_CLIENT) {
                $customer->scopeProjects($q, $user);
            }
        }

        return $q->get()->map(fn (Project $row) => [
            'id' => $row->id,
            'title' => (string) ($row->name ?: '#'.$row->id),
            'name' => (string) ($row->name ?: '#'.$row->id),
            'status' => (string) ($row->status ?? 'active'),
            'subtitle' => (string) ($row->status ?? ''),
            'href' => 'pm/projects',
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mapInvoices(): array
    {
        $items = [];
        if (Schema::hasTable('acc_invoices')) {
            foreach (AccountingInvoice::query()->orderByDesc('id')->limit(8)->get() as $row) {
                $total = (float) ($row->total ?? 0);
                $items[] = [
                    'id' => $row->id,
                    'title' => (string) ($row->number ?: '#'.$row->id),
                    'status' => (string) ($row->status ?? 'draft'),
                    'amount' => $total,
                    'amount_display' => $this->formatMoney($total),
                    'subtitle' => $this->formatMoney($total),
                    'href' => 'finance/invoices',
                ];
            }
        }
        if ($items === [] && Schema::hasTable('sales_invoices')) {
            foreach (SalesInvoice::query()->orderByDesc('id')->limit(8)->get() as $row) {
                $total = (float) ($row->total ?? 0);
                $items[] = [
                    'id' => $row->id,
                    'title' => (string) ($row->number ?? $row->invoice_number ?? $row->project_title ?? '#'.$row->id),
                    'status' => (string) ($row->status ?? 'draft'),
                    'amount' => $total,
                    'amount_display' => $this->formatMoney($total),
                    'subtitle' => $this->formatMoney($total),
                    'href' => 'finance/invoices',
                ];
            }
        }

        return array_slice($items, 0, 8);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mapSites(): array
    {
        if (! Schema::hasTable('webino_site_provisions')) {
            return [];
        }

        return WebinoSiteProvision::query()->orderByDesc('id')->limit(8)->get()->map(fn (WebinoSiteProvision $row) => [
            'id' => $row->id,
            'title' => (string) ($row->domain ?: $row->slug ?: '#'.$row->id),
            'status' => (string) ($row->status ?? 'pending'),
            'subtitle' => (string) ($row->slug ?? ''),
            'href' => 'admin/platform/sites/'.$row->id,
        ])->all();
    }

    private function formatMoney(float $amount, ?string $currency = null): string
    {
        try {
            if (class_exists(Money::class)) {
                // Deal/invoice totals are typically already in display units (not rial base).
                $code = $currency ?: Money::displayCurrency();
                $formatted = Money::formatNumber($amount, 'fa', \App\Support\Currency::decimals($code));

                return trim($formatted.' '.\App\Support\Currency::label($code, 'fa'));
            }
        } catch (\Throwable) {
            // fall through
        }

        return number_format($amount, 0, '.', ',');
    }

    public function crmPm(Request $request, CustomerAccess $customer, StaffOpsService $ops): JsonResponse
    {
        $user = $request->user();
        $role = $this->access->roleFor($user);
        $closed = ['done', 'completed', 'cancelled', 'closed'];
        $open = ProjectTask::query()->whereNotIn('status', $closed);
        if ($user) {
            $customer->scopeTasks($open, $user);
        }

        $extra = ['todos' => ['counts' => [], 'overdue' => [], 'today' => [], 'mine' => [], 'work' => [], 'sites' => []], 'ops' => []];
        try {
            $extra = $ops->enrich($user);
        } catch (\Throwable) {
            // Keep the CRM and PM cards available when a satellite module is missing.
        }

        $crm = [
            'customers' => $this->safeCount(fn () => CrmAccount::query()->count()),
            'deals' => $this->safeCount(fn () => CrmDeal::query()->count()),
            'consultations' => $this->safeCount(fn () => CrmConsultation::query()->count()),
            'pipelines' => $this->safeCount(fn () => CrmPipeline::query()->count()),
            'leads' => $this->safeCount(fn () => CrmLead::query()->count()),
        ];
        $pm = [
            'projects' => $this->safeCount(fn () => Project::query()->count()),
            'open_tasks' => $this->safeCount(fn () => (clone $open)->count()),
            'tickets' => $this->safeCount(fn () => PrjTicket::query()->whereIn('status', ['open', 'pending', 'in_progress'])->count()),
            'contracts' => $this->safeCount(fn () => Contract::query()->count()),
        ];

        $opsPayload = $extra['ops'] ?? [];
        if (! $this->access->canSection($role, 'finance')) {
            unset($opsPayload['finance']);
        }
        if (! $this->access->canSection($role, 'sites')) {
            unset($opsPayload['sites']);
        }
        if (! $this->access->canSection($role, 'marketing')) {
            unset($opsPayload['marketing']);
        }

        return response()->json([
            'data' => [
                'role' => $role,
                'sections' => $this->access->allowedSections($role),
                'crm' => $this->access->canSection($role, 'crm_kpis') ? $crm : [],
                'pm' => $this->access->canSection($role, 'pm_kpis') ? $pm : [],
                'todos' => $this->access->canSection($role, 'todos') ? ($extra['todos'] ?? []) : ['counts' => [], 'work' => [], 'sites' => []],
                'ops' => $opsPayload,
                'suite' => $this->suiteWidgets($role),
            ],
        ]);
    }

    /**
     * @return array<string, int|float|string>
     */
    private function suiteWidgets(?string $role = null): array
    {
        $report = [];
        try {
            $report = app(CrmForecastService::class)->report();
        } catch (\Throwable) {
            $report = [];
        }

        $weighted = (float) ($report['weighted_amount'] ?? 0);
        $won = (float) ($report['won_amount'] ?? 0);
        $lost = (float) ($report['lost_amount'] ?? 0);

        $all = [
            'weighted_forecast' => (int) round($weighted),
            'weighted_forecast_display' => $this->formatMoney($weighted),
            'open_reminders' => $this->safeCount(fn () => CrmActivity::query()->whereNotNull('remind_at')->whereNull('reminded_at')->whereNull('completed_at')->count()),
            'win_amount' => (int) round($won),
            'win_amount_display' => $this->formatMoney($won),
            'loss_amount' => (int) round($lost),
            'loss_amount_display' => $this->formatMoney($lost),
            'win_count' => (int) ($report['won_count'] ?? 0),
            'loss_count' => (int) ($report['lost_count'] ?? 0),
            'delayed_tasks' => $this->safeCount(fn () => ProjectTask::query()->whereNotIn('status', ['done', 'completed', 'cancelled', 'closed'])->whereNotNull('due_at')->where('due_at', '<', now())->count()),
            'sla_breaches' => $this->safeCount(fn () => Schema::hasColumn('prj_tickets', 'sla_breached_at')
                ? PrjTicket::query()->whereNotNull('sla_breached_at')->whereNotIn('status', ['closed', 'resolved'])->count()
                : 0),
            'workload_open' => $this->safeCount(fn () => ProjectTask::query()->whereNotIn('status', ['done', 'completed', 'cancelled', 'closed'])->count()),
        ];

        if ($role === null) {
            return $all;
        }

        $filtered = [];
        $map = [
            'forecast' => ['weighted_forecast', 'weighted_forecast_display'],
            'reminders' => ['open_reminders'],
            'win_loss' => ['win_amount', 'win_amount_display', 'loss_amount', 'loss_amount_display', 'win_count', 'loss_count'],
            'delays' => ['delayed_tasks'],
            'sla' => ['sla_breaches'],
            'workload' => ['workload_open'],
        ];
        foreach ($map as $key => $fields) {
            if (! $this->access->canSuiteWidget($role, $key)) {
                continue;
            }
            foreach ($fields as $field) {
                $filtered[$field] = $all[$field];
            }
        }

        return $filtered;
    }

    public function widgets(Request $request): JsonResponse
    {
        $role = $this->access->roleFor($request->user());
        $catalog = [
            ['key' => 'forecast', 'module' => 'crm'],
            ['key' => 'reminders', 'module' => 'crm'],
            ['key' => 'win_loss', 'module' => 'crm'],
            ['key' => 'delays', 'module' => 'pm'],
            ['key' => 'sla', 'module' => 'pm'],
            ['key' => 'workload', 'module' => 'pm'],
            ['key' => 'recent_leads', 'module' => 'crm'],
            ['key' => 'recent_deals', 'module' => 'crm'],
            ['key' => 'recent_tasks', 'module' => 'pm'],
            ['key' => 'recent_tickets', 'module' => 'pm'],
            ['key' => 'recent_projects', 'module' => 'pm'],
            ['key' => 'recent_invoices', 'module' => 'finance'],
            ['key' => 'recent_sites', 'module' => 'platform'],
        ];
        $prefs = DashboardWidgetPref::query()->where('user_id', $request->user()->id)->get()->keyBy('widget_key');
        $items = collect($catalog)
            ->filter(function (array $item) use ($role) {
                $key = $item['key'];
                if (str_starts_with($key, 'recent_')) {
                    return $this->access->canListWidget($role, $key);
                }

                return $this->access->canSuiteWidget($role, $key);
            })
            ->map(function (array $item) use ($prefs) {
                $pref = $prefs[$item['key']] ?? null;

                return [
                    'key' => $item['key'],
                    'module' => $item['module'],
                    'visible' => $pref ? (bool) $pref->is_visible : true,
                    'sort_order' => $pref ? (int) $pref->sort_order : 0,
                ];
            })->sortBy('sort_order')->values();

        return response()->json([
            'data' => [
                'role' => $role,
                'sections' => $this->access->allowedSections($role),
                'widgets' => $items,
                'metrics' => $this->suiteWidgets($role),
            ],
        ]);
    }

    public function saveWidgets(Request $request): JsonResponse
    {
        $role = $this->access->roleFor($request->user());
        $data = $request->validate([
            'widgets' => 'required|array',
            'widgets.*.key' => 'required|string|max:64',
            'widgets.*.visible' => 'required|boolean',
            'widgets.*.sort_order' => 'nullable|integer|min:0',
        ]);
        foreach ($data['widgets'] as $widget) {
            $key = $widget['key'];
            $allowed = str_starts_with($key, 'recent_')
                ? $this->access->canListWidget($role, $key)
                : $this->access->canSuiteWidget($role, $key);
            if (! $allowed) {
                continue;
            }
            DashboardWidgetPref::query()->updateOrCreate(
                ['user_id' => $request->user()->id, 'widget_key' => $key],
                ['is_visible' => $widget['visible'], 'sort_order' => $widget['sort_order'] ?? 0],
            );
        }

        return $this->widgets($request);
    }

    public function teamMemberStats(): JsonResponse
    {
        $uid = auth()->id();

        return response()->json([
            'data' => [
                'tasks_assigned' => ProjectTask::query()->where('assignee_id', $uid)->count(),
                'tickets_assigned' => PrjTicket::query()->where('assignee_id', $uid)->count(),
            ],
        ]);
    }

    public function clientStats(): JsonResponse
    {
        $user = auth()->user();
        $access = app(CustomerAccess::class);
        $projects = Project::query();
        $tickets = PrjTicket::query()->whereIn('status', ['open', 'pending', 'in_progress']);
        $appointments = \Modules\Projects\Entities\PrjAppointment::query()->where('starts_at', '>=', now());
        if ($user) {
            $access->scopeProjects($projects, $user);
            $access->scopeTickets($tickets, $user);
            $access->scopeAppointments($appointments, $user);
        }

        return response()->json([
            'data' => [
                'projects' => $projects->count(),
                'open_tickets' => $tickets->count(),
                'upcoming_appointments' => $appointments->count(),
            ],
        ]);
    }

    public function logs(Request $request): JsonResponse
    {
        $limit = min((int) $request->input('limit', 100), 500);

        if ((string) $request->input('type', '') === 'bale' && Schema::hasTable('bale_logs')) {
            $rows = DB::table('bale_logs')
                ->orderByDesc('id')
                ->limit($limit)
                ->get();

            return response()->json(['data' => $rows]);
        }

        $rows = DB::table('core_system_logs')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function userLogs(): JsonResponse
    {
        $rows = DB::table('core_system_logs')
            ->where('user_id', auth()->id())
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return response()->json(['data' => $rows]);
    }
}
