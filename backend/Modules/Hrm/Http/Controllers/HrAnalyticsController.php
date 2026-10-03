<?php

namespace Modules\Hrm\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Hrm\Services\HrmAnalyticsService;

class HrAnalyticsController extends Controller
{
    public function summary(Request $request, HrmAnalyticsService $analytics): JsonResponse
    {
        $from = Carbon::parse($request->input('from', now()->startOfYear()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->input('to', now()->toDateString()))->endOfDay();
        abort_if($from->gt($to), 422, 'Invalid range');

        return response()->json(['data' => $analytics->summary($from, $to)]);
    }
}
