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
                'leads_total' => $this->safeCount(fn () => CrmLead::query()->count()),
                'projects_active' => $this->safeCount(fn () => Project::query()->where('status', 'active')->count()),
                'tasks_open' => $this->safeCount(fn () => ProjectTask::query()->where('status', '!=', 'done')->count()),
                'tickets_open' => $this->safeCount(fn () => PrjTicket::query()->whereIn('status', ['open', 'pending', 'in_progress'])->count()),
                'contracts_total' => \Illuminate\Support\Facades\Schema::hasTable('prj_contracts')
                    ? $this->safeCount(fn () => DB::table('prj_contracts')->count())
                    : 0,
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
}
