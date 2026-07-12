<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Modules\Crm\Entities\CrmLead;
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
                        'items' => CrmLead::query()->orderByDesc('id')->limit(5)->get(),
                    ],
                    [
                        'id' => 'recent_tasks',
                        'title' => 'آخرین وظایف',
                        'type' => 'list',
                        'items' => ProjectTask::query()->orderByDesc('id')->limit(5)->get(),
                    ],
                    [
                        'id' => 'recent_tickets',
                        'title' => 'آخرین تیکت‌ها',
                        'type' => 'list',
                        'items' => PrjTicket::query()->orderByDesc('id')->limit(5)->get(),
                    ],
                ],
                'stats' => [
                    'leads' => CrmLead::query()->count(),
                    'projects' => Project::query()->count(),
                    'tasks_open' => ProjectTask::query()->where('status', '!=', 'done')->count(),
                    'tickets_open' => PrjTicket::query()->where('status', 'open')->count(),
                    'contracts' => Contract::query()->count(),
                ],
            ],
        ]);
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
        $uid = auth()->id();

        return response()->json([
            'data' => [
                'projects' => Project::query()->where('created_by', $uid)->count(),
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
