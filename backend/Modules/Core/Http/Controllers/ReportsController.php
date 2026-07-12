<?php

namespace Modules\Core\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Crm\Entities\CrmLead;
use Modules\Projects\Entities\Contract;
use Modules\Projects\Entities\PrjSprint;
use Modules\Projects\Entities\PrjTicket;
use Modules\Projects\Entities\ProjectTask;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $from = $request->date('from') ?? now()->subMonth();
        $to = $request->date('to') ?? now();

        return response()->json([
            'data' => [
                'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
                'contracts_total' => Contract::query()->whereBetween('created_at', [$from, $to])->count(),
                'tasks_completed' => ProjectTask::query()->where('status', 'done')->whereBetween('updated_at', [$from, $to])->count(),
                'leads_new' => CrmLead::query()->whereBetween('created_at', [$from, $to])->count(),
                'tickets_closed' => PrjTicket::query()->where('status', 'closed')->whereBetween('updated_at', [$from, $to])->count(),
                'sprints_started' => PrjSprint::query()->whereBetween('created_at', [$from, $to])->count(),
                'series' => $this->buildSeries($from, $to),
            ],
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $from = $request->date('from') ?? now()->subMonth();
        $to = $request->date('to') ?? now();

        $rows = [
            ['metric', 'value'],
            ['contracts_total', (string) Contract::query()->whereBetween('created_at', [$from, $to])->count()],
            ['tasks_completed', (string) ProjectTask::query()->where('status', 'done')->whereBetween('updated_at', [$from, $to])->count()],
            ['leads_new', (string) CrmLead::query()->whereBetween('created_at', [$from, $to])->count()],
            ['tickets_closed', (string) PrjTicket::query()->where('status', 'closed')->whereBetween('updated_at', [$from, $to])->count()],
            ['sprints_started', (string) PrjSprint::query()->whereBetween('created_at', [$from, $to])->count()],
            ['from', $from->toDateString()],
            ['to', $to->toDateString()],
        ];

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            foreach ($rows as $r) {
                fputcsv($out, $r);
            }
            fclose($out);
        }, 'reports-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array<string, list<array{day: string, count: int}>>
     */
    private function buildSeries(Carbon $from, Carbon $to): array
    {
        $fromDay = $from->copy()->startOfDay();
        $toDay = $to->copy()->endOfDay();

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

        return [
            'contracts' => $mapSeries($contracts),
            'tasks_completed' => $mapSeries($tasks),
            'leads' => $mapSeries($leads),
            'tickets_closed' => $mapSeries($tickets),
            'sprints' => $mapSeries($sprints),
        ];
    }
}
