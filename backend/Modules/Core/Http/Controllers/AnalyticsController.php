<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Crm\Entities\CrmLead;

class AnalyticsController extends Controller
{
    public function kpi(Request $request): JsonResponse
    {
        $data = Cache::remember('analytics:kpi:v1', 300, function () {
            return [
                'leads_total' => CrmLead::query()->count(),
                'leads_open' => CrmLead::query()->whereNull('converted_at')->count(),
                'users_total' => (int) DB::table('users')->count(),
            ];
        });

        return response()->json(['data' => $data]);
    }

    public function funnel(Request $request): JsonResponse
    {
        $rows = DB::table('crm_leads')
            ->join('crm_statuses', 'crm_leads.status_id', '=', 'crm_statuses.id')
            ->select('crm_statuses.name as stage', DB::raw('count(*) as count'))
            ->groupBy('crm_statuses.name', 'crm_statuses.sort_order')
            ->orderBy('crm_statuses.sort_order')
            ->get();

        return response()->json([
            'data' => [
                'stages' => $rows->pluck('stage')->values(),
                'counts' => $rows->pluck('count')->map(fn ($c) => (int) $c)->values(),
            ],
        ]);
    }

    public function cohort(Request $request): JsonResponse
    {
        $monthExpr = DB::getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', created_at)"
            : "to_char(created_at, 'YYYY-MM')";

        $rows = DB::table('crm_leads')
            ->select(
                DB::raw("{$monthExpr} as cohort"),
                DB::raw('count(*) as total'),
                DB::raw('sum(case when converted_at is not null then 1 else 0 end) as converted')
            )
            ->groupBy('cohort')
            ->orderBy('cohort')
            ->get();

        return response()->json([
            'data' => [
                'cohorts' => $rows->map(fn ($r) => [
                    'month' => $r->cohort,
                    'total' => (int) $r->total,
                    'converted' => (int) $r->converted,
                ])->values(),
            ],
        ]);
    }
}
