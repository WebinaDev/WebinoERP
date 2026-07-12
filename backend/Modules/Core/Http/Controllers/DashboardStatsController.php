<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Modules\Crm\Entities\CrmLead;
use Modules\Projects\Entities\PrjTicket;
use Modules\Projects\Entities\Project;
use Modules\Projects\Entities\ProjectTask;

class DashboardStatsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'data' => [
                'leads_total' => CrmLead::query()->count(),
                'projects_active' => Project::query()->where('status', 'active')->count(),
                'tasks_open' => ProjectTask::query()->where('status', '!=', 'done')->count(),
                'tickets_open' => PrjTicket::query()->whereIn('status', ['open', 'pending', 'in_progress'])->count(),
                'contracts_total' => DB::table('prj_contracts')->count(),
            ],
        ]);
    }
}
