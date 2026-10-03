<?php

namespace Modules\Core\Services;

use App\Models\User;
use App\Support\CustomerAccess;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Accounting\Entities\AccountingInvoice;
use Modules\Accounting\Entities\AccReceiptVoucher;
use Modules\Core\Database\Seeders\RolesAndPermissionsSeeder;
use Modules\Core\Entities\CoreNotification;
use Modules\Crm\Entities\ContentItem;
use Modules\Crm\Entities\CrmActivity;
use Modules\Crm\Entities\CrmConsultation;
use Modules\Crm\Entities\CrmDeal;
use Modules\Crm\Entities\CrmLead;
use Modules\Marketing\Entities\MarketingAnnouncement;
use Modules\Marketing\Entities\MarketingBlogPost;
use Modules\Marketing\Entities\MarketingFormSubmission;
use Modules\Marketing\Entities\MarketingPage;
use Modules\Projects\Entities\PrjApproval;
use Modules\Projects\Entities\PrjTicket;
use Modules\Projects\Entities\Project;
use Modules\Projects\Entities\ProjectTask;
use Modules\Sales\Entities\SalesInvoice;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;

/**
 * Staff dashboard and report slices: CRM, PM, finance, tickets, sites, marketing, notifications.
 */
class StaffOpsService
{
    /** @var list<string> */
    private const CLOSED_TASKS = ['done', 'completed', 'cancelled', 'closed'];

    /** @var list<string> */
    private const CLOSED_CONSULTATIONS = ['converted', 'closed', 'done', 'cancelled', 'lost'];

    public function __construct(private readonly CustomerAccess $access) {}

    /**
     * @return array{todos: array<string, mixed>, ops: array<string, mixed>}
     */
    public function enrich(?User $user): array
    {
        return [
            'todos' => $this->todos($user),
            'ops' => $this->ops($user),
        ];
    }

    /**
     * @return array{stats: array<string, mixed>, charts: array<string, mixed>, tables: array<string, mixed>}
     */
    public function report(string $tab, Carbon $from, Carbon $to): array
    {
        return match ($tab) {
            'sites' => $this->sitesReport($from, $to),
            'marketing' => $this->marketingReport($from, $to),
            'notifications' => $this->notificationsReport($from, $to),
            'finance' => $this->financeReport($from, $to),
            default => $this->overviewExtras($from, $to),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function todos(?User $user): array
    {
        $start = now()->startOfDay();
        $end = now()->endOfDay();
        $horizon = now()->addDays(7)->endOfDay();

        $open = ProjectTask::query()->whereNotIn('status', self::CLOSED_TASKS);
        if ($user) {
            $this->access->scopeTasks($open, $user);
            if (! $this->access->isPortalCustomer($user)) {
                $open->where('assignee_id', $user->id);
            }
        }

        $mapTask = static fn (ProjectTask $task) => [
            'id' => $task->id,
            'title' => $task->title,
            'status' => $task->status,
            'priority' => $task->priority,
            'due_at' => optional($task->due_at)?->utc()->toIso8601String(),
            'project_id' => $task->project_id,
        ];

        $overdue = (clone $open)->whereNotNull('due_at')->where('due_at', '<', $start)->orderBy('due_at');
        $today = (clone $open)->whereBetween('due_at', [$start, $end])->orderBy('due_at');
        $mine = (clone $open)->orderByDesc('id');

        $work = [];
        foreach ((clone $overdue)->limit(8)->get() as $task) {
            $work[] = $this->item('task', (int) $task->id, (string) $task->title, optional($task->due_at)?->utc()->toIso8601String(), (string) $task->status, 'overdue', 'pm/tasks');
        }
        foreach ((clone $today)->limit(8)->get() as $task) {
            $work[] = $this->item('task', (int) $task->id, (string) $task->title, optional($task->due_at)?->utc()->toIso8601String(), (string) $task->status, 'today', 'pm/tasks');
        }
        foreach ((clone $open)->whereNotNull('due_at')->where('due_at', '>', $end)->where('due_at', '<=', $horizon)->orderBy('due_at')->limit(8)->get() as $task) {
            $work[] = $this->item('task', (int) $task->id, (string) $task->title, optional($task->due_at)?->utc()->toIso8601String(), (string) $task->status, 'upcoming', 'pm/tasks');
        }
        foreach ((clone $open)->whereNull('due_at')->orderByDesc('id')->limit(8)->get() as $task) {
            $work[] = $this->item('task', (int) $task->id, (string) $task->title, null, (string) $task->status, 'upcoming', 'pm/tasks');
        }

        if ($user) {
            $work = array_merge($work, $this->consultations($user, $start), $this->dealFollowUps($user, $start, $end, $horizon), $this->approvals($user, $start));
        }

        $sites = $this->problemSites($user);

        return [
            'counts' => [
                'overdue' => (clone $overdue)->count(),
                'today' => (clone $today)->count(),
                'mine' => (clone $mine)->count(),
                'problems' => count($sites),
            ],
            'overdue' => $overdue->limit(8)->get()->map($mapTask)->values(),
            'today' => $today->limit(8)->get()->map($mapTask)->values(),
            'mine' => $mine->limit(8)->get()->map($mapTask)->values(),
            'work' => array_values($work),
            'sites' => $sites,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function consultations(User $user, Carbon $start): array
    {
        if (! Schema::hasTable('crm_consultations')) {
            return [];
        }

        $rows = CrmConsultation::query()
            ->where('created_by', $user->id)
            ->whereNotIn('status', self::CLOSED_CONSULTATIONS)
            ->orderBy('created_at')
            ->limit(8)
            ->get();

        $items = [];
        foreach ($rows as $row) {
            $created = $row->created_at;
            $bucket = $created && $created->lt($start) ? 'overdue' : 'today';
            $items[] = $this->item(
                'consultation',
                (int) $row->id,
                (string) $row->title,
                optional($created)?->utc()->toIso8601String(),
                (string) $row->status,
                $bucket,
                'crm/consultations',
            );
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function dealFollowUps(User $user, Carbon $start, Carbon $end, Carbon $horizon): array
    {
        $items = [];
        if (Schema::hasTable('crm_deals')) {
            $deals = CrmDeal::query()
                ->where('assigned_to', $user->id)
                ->whereNull('won_at')
                ->whereNull('lost_at')
                ->whereNotNull('close_date')
                ->where('close_date', '<=', $horizon->toDateString())
                ->orderBy('close_date')
                ->limit(8)
                ->get();
            foreach ($deals as $deal) {
                $due = $deal->close_date ? Carbon::parse($deal->close_date)->startOfDay() : null;
                $items[] = $this->item(
                    'deal',
                    (int) $deal->id,
                    (string) $deal->name,
                    $due?->utc()->toIso8601String(),
                    'open',
                    $this->bucketFor($due, $start, $end),
                    'crm/deals',
                );
            }
        }

        if (Schema::hasTable('crm_activities')) {
            $activities = CrmActivity::query()
                ->where(function ($query) use ($user) {
                    $query->where('assigned_to', $user->id)->orWhere('created_by', $user->id);
                })
                ->whereNull('completed_at')
                ->where(function ($query) {
                    $query->where('type', 'follow_up')
                        ->orWhere('related_model', 'like', '%Deal%');
                })
                ->orderBy('id')
                ->limit(20)
                ->get();
            foreach ($activities as $activity) {
                $due = $activity->due_date
                    ? Carbon::parse($activity->due_date)->startOfDay()
                    : ($activity->scheduled_at ?? $activity->remind_at);
                if (! $due || $due->gt($horizon)) {
                    continue;
                }
                $items[] = $this->item(
                    'deal',
                    (int) $activity->id,
                    (string) ($activity->subject ?: $activity->type),
                    $due->copy()->utc()->toIso8601String(),
                    (string) ($activity->type ?: 'follow_up'),
                    $this->bucketFor($due, $start, $end),
                    'crm/deals',
                );
            }
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function approvals(User $user, Carbon $start): array
    {
        if (! Schema::hasTable('prj_approvals')) {
            return [];
        }

        $query = PrjApproval::query()->where('status', 'pending');
        $seesAll = $user->hasRole(RolesAndPermissionsSeeder::ROLE_SYSTEM_MANAGER)
            || $user->hasRole(RolesAndPermissionsSeeder::ROLE_PROJECT_MANAGER);
        if (! $seesAll) {
            $query->where(function ($inner) use ($user) {
                $inner->where('created_by', $user->id);
                if (Schema::hasColumn('prj_projects', 'manager_user_id')) {
                    $inner->orWhereIn('project_id', Project::query()->where('manager_user_id', $user->id)->select('id'));
                }
            });
        }

        $items = [];
        foreach ($query->orderBy('id')->limit(8)->get() as $row) {
            $created = $row->created_at;
            $items[] = $this->item(
                'approval',
                (int) $row->id,
                (string) $row->title,
                optional($created)?->utc()->toIso8601String(),
                (string) $row->status,
                $created && $created->lt($start) ? 'overdue' : 'today',
                'pm/projects/'.$row->project_id,
            );
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function problemSites(?User $user): array
    {
        if (! Schema::hasTable('webino_site_provisions')) {
            return [];
        }

        $query = WebinoSiteProvision::query()->where('status', '!=', 'cancelled');
        if ($user && $this->access->isPortalCustomer($user)) {
            $ids = $this->access->accountIds($user);
            $query->whereIn('crm_account_id', $ids === [] ? [0] : $ids);
        }

        $rows = $query->orderByDesc('id')->limit(40)->get();
        $items = [];
        foreach ($rows as $site) {
            $reasons = $this->siteReasons($site);
            if ($reasons === []) {
                continue;
            }
            $items[] = [
                'kind' => 'site',
                'id' => (int) $site->id,
                'title' => (string) ($site->domain ?: $site->slug),
                'domain' => (string) $site->domain,
                'status' => (string) $site->status,
                'reasons' => $reasons,
                'error' => $this->excerpt((string) ($site->error_log ?? '')),
                'bucket' => 'problem',
                'href' => 'admin/platform/sites/'.$site->id,
            ];
            if (count($items) >= 8) {
                break;
            }
        }

        return $items;
    }

    private function problemSiteCount(): int
    {
        if (! Schema::hasTable('webino_site_provisions')) {
            return 0;
        }

        try {
            return WebinoSiteProvision::query()
                ->where('status', '!=', 'cancelled')
                ->where(function ($query) {
                    $query->whereIn('status', ['failed', 'ssl_pending'])
                        ->orWhere(function ($inner) {
                            $inner->whereNotNull('error_log')->where('error_log', '!=', '');
                        });
                })
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * @return list<string>
     */
    private function siteReasons(WebinoSiteProvision $site): array
    {
        $reasons = [];
        if (in_array($site->status, ['failed', 'ssl_pending'], true)) {
            $reasons[] = (string) $site->status;
        }
        if (trim((string) $site->error_log) !== '') {
            $reasons[] = 'error';
        }
        if ($site->license_id && Schema::hasTable('core_licenses')) {
            $license = DB::table('core_licenses')->where('id', $site->license_id)->first();
            if ($license && (($license->status ?? '') === 'expired' || ($license->expires_at && Carbon::parse($license->expires_at)->isPast()))) {
                $reasons[] = 'license_expired';
            }
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @return array<string, mixed>
     */
    private function ops(?User $user): array
    {
        $from = now()->startOfMonth();
        $to = now();

        return [
            'finance' => $this->financeReport($from, $to)['stats'],
            'tickets' => $this->ticketStats($user),
            'sites' => $this->sitesReport($from, $to)['stats'],
            'marketing' => $this->marketingReport($from, $to)['stats'],
            'notifications' => [
                'unread' => $this->safeCount(fn () => $user
                    ? CoreNotification::query()->where('user_id', $user->id)->where('is_read', false)->count()
                    : 0),
                'recent' => $this->recentNotifications($user, 6),
            ],
            'trend' => $this->trend(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function ticketStats(?User $user): array
    {
        $openStatuses = ['open', 'pending', 'in_progress'];
        $open = PrjTicket::query()->whereIn('status', $openStatuses);
        if ($user) {
            $this->access->scopeTickets($open, $user);
        }

        return [
            'open' => $this->safeCount(fn () => (clone $open)->count()),
            'sla_breaches' => $this->safeCount(function () use ($user) {
                if (! Schema::hasColumn('prj_tickets', 'sla_breached_at')) {
                    return 0;
                }
                $query = PrjTicket::query()->whereNotNull('sla_breached_at')->whereNotIn('status', ['closed', 'resolved']);
                if ($user) {
                    $this->access->scopeTickets($query, $user);
                }

                return $query->count();
            }),
            'mine' => $this->safeCount(fn () => $user
                ? (clone $open)->where('assignee_id', $user->id)->count()
                : 0),
        ];
    }

    /**
     * @return array{stats: array<string, mixed>, charts: array<string, mixed>, tables: array<string, mixed>}
     */
    private function overviewExtras(Carbon $from, Carbon $to): array
    {
        $sites = $this->sitesReport($from, $to);
        $marketing = $this->marketingReport($from, $to);
        $notifications = $this->notificationsReport($from, $to);
        $finance = $this->financeReport($from, $to);

        return [
            'stats' => array_merge($sites['stats'], $marketing['stats'], $notifications['stats'], $finance['stats']),
            'charts' => [
                'marketing_daily' => $marketing['charts']['daily'] ?? [],
            ],
            'tables' => [
                'problem_sites' => $sites['tables']['problem_sites'] ?? [],
                'recent_notifications' => $notifications['tables']['recent_notifications'] ?? [],
            ],
        ];
    }

    /**
     * @return array{stats: array<string, mixed>, charts: array<string, mixed>, tables: array<string, mixed>}
     */
    private function financeReport(Carbon $from, Carbon $to): array
    {
        $invoiceCount = 0;
        $invoiceTotal = 0.0;
        $openInvoices = 0;
        if (Schema::hasTable('acc_invoices')) {
            $invoiceCount += $this->safeCount(fn () => AccountingInvoice::query()->whereBetween('created_at', [$from, $to])->count());
            $invoiceTotal += $this->safeSum(fn () => (float) AccountingInvoice::query()->whereBetween('created_at', [$from, $to])->sum('total'));
            $openInvoices += $this->safeCount(fn () => AccountingInvoice::query()->whereBetween('created_at', [$from, $to])->whereNotIn('status', ['paid', 'void', 'cancelled'])->count());
        }
        if (Schema::hasTable('sales_invoices')) {
            $invoiceCount += $this->safeCount(fn () => SalesInvoice::query()->whereBetween('created_at', [$from, $to])->count());
            $invoiceTotal += $this->safeSum(fn () => (float) SalesInvoice::query()->whereBetween('created_at', [$from, $to])->sum('total'));
            $openInvoices += $this->safeCount(fn () => SalesInvoice::query()->whereBetween('created_at', [$from, $to])->whereNotIn('status', ['paid', 'void', 'cancelled'])->count());
        }

        return [
            'stats' => [
                'invoice_count' => $invoiceCount,
                'invoice_total' => round($invoiceTotal, 2),
                'open_invoices' => $openInvoices,
                'receipts_count' => $this->safeCount(fn () => Schema::hasTable('acc_receipt_vouchers')
                    ? AccReceiptVoucher::query()->whereBetween('created_at', [$from, $to])->count()
                    : 0),
            ],
            'charts' => [],
            'tables' => [],
        ];
    }

    /**
     * @return array{stats: array<string, mixed>, charts: array<string, mixed>, tables: array<string, mixed>}
     */
    private function sitesReport(Carbon $from, Carbon $to): array
    {
        $problems = $this->problemSites(null);

        return [
            'stats' => [
                'sites_total' => $this->safeCount(fn () => WebinoSiteProvision::query()->count()),
                'sites_ready' => $this->safeCount(fn () => WebinoSiteProvision::query()->where('status', 'ready')->count()),
                'sites_failed' => $this->safeCount(fn () => WebinoSiteProvision::query()->where('status', 'failed')->count()),
                'sites_problems' => $this->problemSiteCount(),
                'sites_created' => $this->safeCount(fn () => WebinoSiteProvision::query()->whereBetween('created_at', [$from, $to])->count()),
            ],
            'charts' => [],
            'tables' => [
                'problem_sites' => array_map(static fn (array $site) => [
                    'domain' => $site['domain'],
                    'status' => $site['status'],
                    'reason' => implode(',', $site['reasons']),
                ], $problems),
            ],
        ];
    }

    /**
     * @return array{stats: array<string, mixed>, charts: array<string, mixed>, tables: array<string, mixed>}
     */
    private function marketingReport(Carbon $from, Carbon $to): array
    {
        $daily = [];
        if (Schema::hasTable('marketing_form_submissions')) {
            $grouped = MarketingFormSubmission::query()
                ->whereBetween('created_at', [$from, $to])
                ->get(['created_at'])
                ->groupBy(fn ($row) => optional($row->created_at)?->toDateString());
            foreach ($grouped as $day => $rows) {
                if (! $day) {
                    continue;
                }
                $daily[] = ['date' => $day, 'total' => $rows->count()];
            }
        }

        return [
            'stats' => [
                'published_pages' => $this->safeCount(fn () => MarketingPage::query()->where('status', 'published')->count()),
                'form_submissions' => $this->safeCount(fn () => MarketingFormSubmission::query()->whereBetween('created_at', [$from, $to])->count()),
                'published_posts' => $this->safeCount(fn () => MarketingBlogPost::query()->where('status', 'published')->count()),
                'announcements' => $this->safeCount(fn () => MarketingAnnouncement::query()->where('published', true)->count()),
                'scheduled_content' => $this->safeCount(fn () => Schema::hasTable('crm_content_items')
                    ? ContentItem::query()->where('status', '!=', 'published')->whereNotNull('publish_start')->count()
                    : 0),
                'leads_in_range' => $this->safeCount(fn () => CrmLead::query()->whereBetween('created_at', [$from, $to])->count()),
            ],
            'charts' => ['daily' => $daily],
            'tables' => [],
        ];
    }

    /**
     * @return array{stats: array<string, mixed>, charts: array<string, mixed>, tables: array<string, mixed>}
     */
    private function notificationsReport(Carbon $from, Carbon $to): array
    {
        return [
            'stats' => [
                'notifications_total' => $this->safeCount(fn () => CoreNotification::query()->whereBetween('created_at', [$from, $to])->count()),
                'unread_notifications' => $this->safeCount(fn () => CoreNotification::query()->where('is_read', false)->count()),
            ],
            'charts' => [],
            'tables' => [
                'recent_notifications' => $this->recentNotifications(null, 12, $from, $to),
            ],
        ];
    }

    /**
     * @return list<array{title: string, created_at: ?string, is_read: bool}>
     */
    private function recentNotifications(?User $user, int $limit, ?Carbon $from = null, ?Carbon $to = null): array
    {
        if (! Schema::hasTable('core_notifications')) {
            return [];
        }

        try {
            $query = CoreNotification::query()->orderByDesc('id');
            if ($user) {
                $query->where('user_id', $user->id);
            }
            if ($from && $to) {
                $query->whereBetween('created_at', [$from, $to]);
            }

            return $query->limit($limit)->get()->map(function (CoreNotification $row) {
                $data = is_array($row->data) ? $row->data : [];

                return [
                    'title' => (string) ($data['title'] ?? ''),
                    'created_at' => optional($row->created_at)?->utc()->toIso8601String(),
                    'is_read' => (bool) $row->is_read,
                ];
            })->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return list<array{date: string, total: int}>
     */
    private function trend(): array
    {
        $from = now()->subDays(13)->startOfDay();
        $to = now()->endOfDay();
        $tasks = $this->dailyCounts('prj_tasks', $from, $to);
        $leads = $this->dailyCounts('crm_leads', $from, $to);
        $tickets = $this->dailyCounts('prj_tickets', $from, $to);
        $out = [];
        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            $day = $cursor->toDateString();
            $out[] = [
                'date' => $day,
                'total' => (int) ($tasks[$day] ?? 0) + (int) ($leads[$day] ?? 0) + (int) ($tickets[$day] ?? 0),
            ];
            $cursor->addDay();
        }

        return $out;
    }

    /**
     * @return array<string, int>
     */
    private function dailyCounts(string $table, Carbon $from, Carbon $to): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }

        try {
            return DB::table($table)
                ->whereBetween('created_at', [$from, $to])
                ->selectRaw('DATE(created_at) as day, COUNT(*) as c')
                ->groupBy('day')
                ->pluck('c', 'day')
                ->map(fn ($count) => (int) $count)
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function bucketFor(?Carbon $due, Carbon $start, Carbon $end): string
    {
        if (! $due || $due->lt($start)) {
            return 'overdue';
        }
        if ($due->lte($end)) {
            return 'today';
        }

        return 'upcoming';
    }

    /**
     * @return array<string, mixed>
     */
    private function item(string $kind, int $id, string $title, ?string $dueAt, string $status, string $bucket, string $href): array
    {
        return [
            'kind' => $kind,
            'id' => $id,
            'title' => $title,
            'due_at' => $dueAt,
            'status' => $status,
            'bucket' => $bucket,
            'href' => $href,
        ];
    }

    private function excerpt(string $value): string
    {
        $flat = trim(preg_replace('/\s+/', ' ', $value) ?? '');

        return mb_substr($flat, 0, 160);
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
