<?php

namespace Modules\Core\Http\Controllers;

use App\Support\CustomerAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Modules\Crm\Entities\CrmAccount;
use Modules\Crm\Entities\CrmConsultation;
use Modules\Crm\Entities\CrmDeal;
use Modules\Crm\Entities\CrmLead;
use Modules\Crm\Entities\CrmPipeline;
use Modules\Core\Entities\DashboardWidgetPref;
use Modules\Crm\Entities\CrmActivity;
use Modules\Core\Services\StaffOpsService;
use Modules\Crm\Services\CrmForecastService;
use Modules\Projects\Entities\Contract;
use Modules\Projects\Entities\PrjSprint;
use Modules\Projects\Entities\PrjTicket;
use Modules\Projects\Entities\Project;
use Modules\Projects\Entities\ProjectTask;
use Illuminate\Support\Facades\Schema;

class DashboardParityController extends Controller
{
    public function full(): JsonResponse
    {
        return response()->json([
            'data' => [
                'widgets' => [
                    [
                        'id' => 'recent_leads',
                        'title' => 'آخرین سرنخ‌ها',
                        'type' => 'list',
                        'items' => $this->safeList(fn () => CrmLead::query()->orderByDesc('id')->limit(5)->get()),
                    ],
                    [
                        'id' => 'recent_tasks',
                        'title' => 'آخرین وظایف',
                        'type' => 'list',
                        'items' => $this->safeList(fn () => ProjectTask::query()->orderByDesc('id')->limit(5)->get()),
                    ],
                    [
                        'id' => 'recent_tickets',
                        'title' => 'آخرین تیکت‌ها',
                        'type' => 'list',
                        'items' => $this->safeList(fn () => PrjTicket::query()->orderByDesc('id')->limit(5)->get()),
                    ],
                ],
                'stats' => [
                    'leads' => $this->safeCount(fn () => CrmLead::query()->count()),
                    'projects' => $this->safeCount(fn () => Project::query()->count()),
                    'tasks_open' => $this->safeCount(fn () => ProjectTask::query()->where('status', '!=', 'done')->count()),
                    'tickets_open' => $this->safeCount(fn () => PrjTicket::query()->where('status', 'open')->count()),
                    'contracts' => $this->safeCount(fn () => Contract::query()->count()),
                ],
            ],
        ]);
    }

    private function safeCount(callable $fn): int
    {
        try {
            return (int) $fn();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function safeList(callable $fn): array
    {
        try {
            return $fn()->all();
        } catch (\Throwable) {
            return [];
        }
    }

    public function crmPm(Request $request, CustomerAccess $access, StaffOpsService $ops): JsonResponse
    {
        $user = $request->user();
        $closed = ['done', 'completed', 'cancelled', 'closed'];
        $open = ProjectTask::query()->whereNotIn('status', $closed);
        if ($user) {
            $access->scopeTasks($open, $user);
        }

        $extra = ['todos' => ['counts' => [], 'overdue' => [], 'today' => [], 'mine' => [], 'work' => [], 'sites' => []], 'ops' => []];
        try {
            $extra = $ops->enrich($user);
        } catch (\Throwable) {
            // Keep the CRM and PM cards available when a satellite module is missing.
        }

        return response()->json([
            'data' => [
                'crm' => [
                    'customers' => $this->safeCount(fn () => CrmAccount::query()->count()),
                    'deals' => $this->safeCount(fn () => CrmDeal::query()->count()),
                    'consultations' => $this->safeCount(fn () => CrmConsultation::query()->count()),
                    'pipelines' => $this->safeCount(fn () => CrmPipeline::query()->count()),
                    'leads' => $this->safeCount(fn () => CrmLead::query()->count()),
                ],
                'pm' => [
                    'projects' => $this->safeCount(fn () => Project::query()->count()),
                    'open_tasks' => $this->safeCount(fn () => (clone $open)->count()),
                    'tickets' => $this->safeCount(fn () => PrjTicket::query()->whereIn('status', ['open', 'pending', 'in_progress'])->count()),
                    'contracts' => $this->safeCount(fn () => Contract::query()->count()),
                ],
                'todos' => $extra['todos'],
                'ops' => $extra['ops'],
                'suite' => $this->suiteWidgets(),
            ],
        ]);
    }

    /**
     * @return array<string, int|float>
     */
    private function suiteWidgets(): array
    {
        return [
            'weighted_forecast' => $this->safeCount(function () {
                $report = app(CrmForecastService::class)->report();

                return (int) round((float) ($report['weighted_amount'] ?? 0));
            }),
            'open_reminders' => $this->safeCount(fn () => CrmActivity::query()->whereNotNull('remind_at')->whereNull('reminded_at')->whereNull('completed_at')->count()),
            'delayed_tasks' => $this->safeCount(fn () => ProjectTask::query()->whereNotIn('status', ['done', 'completed', 'cancelled', 'closed'])->whereNotNull('due_at')->where('due_at', '<', now())->count()),
            'sla_breaches' => $this->safeCount(fn () => Schema::hasColumn('prj_tickets', 'sla_breached_at')
                ? PrjTicket::query()->whereNotNull('sla_breached_at')->whereNotIn('status', ['closed', 'resolved'])->count()
                : 0),
        ];
    }

    public function widgets(Request $request): JsonResponse
    {
        $catalog = [
            ['key' => 'forecast', 'module' => 'crm'],
            ['key' => 'reminders', 'module' => 'crm'],
            ['key' => 'win_loss', 'module' => 'crm'],
            ['key' => 'delays', 'module' => 'pm'],
            ['key' => 'sla', 'module' => 'pm'],
            ['key' => 'workload', 'module' => 'pm'],
        ];
        $prefs = DashboardWidgetPref::query()->where('user_id', $request->user()->id)->get()->keyBy('widget_key');
        $items = collect($catalog)->map(function (array $item) use ($prefs) {
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
                'widgets' => $items,
                'metrics' => $this->suiteWidgets(),
            ],
        ]);
    }

    public function saveWidgets(Request $request): JsonResponse
    {
        $data = $request->validate([
            'widgets' => 'required|array',
            'widgets.*.key' => 'required|string|max:64',
            'widgets.*.visible' => 'required|boolean',
            'widgets.*.sort_order' => 'nullable|integer|min:0',
        ]);
        foreach ($data['widgets'] as $widget) {
            DashboardWidgetPref::query()->updateOrCreate(
                ['user_id' => $request->user()->id, 'widget_key' => $widget['key']],
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
        $access = app(\App\Support\CustomerAccess::class);
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
