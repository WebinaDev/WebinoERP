<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VisitorStatsController extends Controller
{
    public function track(Request $request): JsonResponse
    {
        $request->validate([
            'path' => 'nullable|string|max:500',
            'title' => 'nullable|string|max:191',
            'ms_on_page' => 'nullable|integer|min:0|max:86400000',
            'session_id' => 'nullable|string|max:64',
            'country' => 'nullable|string|max:2',
        ]);

        DB::table('core_visitor_events')->insert([
            'path' => $request->input('path'),
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 2000),
            'user_id' => $request->user()?->id,
            'visited_at' => now(),
        ]);

        $visitId = DB::table('core_visits')->insertGetId([
            'session_id' => $request->input('session_id'),
            'user_id' => $request->user()?->id,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 2000),
            'country' => $request->input('country'),
            'device' => self::parseDevice((string) $request->userAgent()),
            'referrer' => $request->headers->get('referer'),
            'landing_path' => $request->input('path'),
            'created_at' => now(),
        ]);

        if ($request->filled('path')) {
            DB::table('core_visitor_pages')->insert([
                'visit_id' => $visitId,
                'path' => $request->input('path'),
                'title' => $request->input('title'),
                'ms_on_page' => (int) $request->input('ms_on_page', 0),
                'created_at' => now(),
            ]);
        }

        return response()->json(['data' => ['recorded' => true]]);
    }

    public function index(Request $request): JsonResponse
    {
        $days = min((int) $request->input('days', 7), 90);
        $since = now()->subDays($days);

        $base = DB::table('core_visits')->where('created_at', '>=', $since);

        $total = (clone $base)->count();
        $uniqueVisitors = (clone $base)->whereNotNull('ip')->distinct('ip')->count('ip');

        $fromDate = $since->toDateString();
        $toDate = now()->toDateString();
        $dailyRows = DB::table('core_visitor_daily')
            ->whereBetween('date', [$fromDate, $toDate])
            ->orderBy('date')
            ->get();

        if ($dailyRows->isNotEmpty()) {
            $byDay = $dailyRows->map(fn ($row) => (object) ['day' => $row->date, 'visits' => (int) $row->visits]);
            $total = (int) $dailyRows->sum('visits');
            $uniqueVisitors = (int) $dailyRows->sum('uniques');
            $pageviews = (int) $dailyRows->sum('pageviews');
            $dataSource = 'aggregate';
        } else {
            $byDay = DB::table('core_visits')
                ->where('created_at', '>=', $since)
                ->selectRaw('DATE(created_at) as day, COUNT(*) as visits')
                ->groupBy('day')
                ->orderBy('day')
                ->get();
            $pageviews = DB::table('core_visitor_pages')->where('created_at', '>=', $since)->count();
            $dataSource = 'raw';
        }

        $topPaths = DB::table('core_visitor_pages')
            ->where('created_at', '>=', $since)
            ->selectRaw('path, COUNT(*) as visits')
            ->groupBy('path')
            ->orderByDesc('visits')
            ->limit(15)
            ->get();

        $recent = DB::table('core_visits')
            ->where('created_at', '>=', $since)
            ->orderByDesc('created_at')
            ->limit(25)
            ->get();

        $uaRows = DB::table('core_visits')
            ->where('created_at', '>=', $since)
            ->whereNotNull('user_agent')
            ->where('user_agent', '!=', '')
            ->pluck('user_agent');

        $browsers = [];
        $oses = [];
        $devices = [];
        foreach ($uaRows as $ua) {
            $b = self::parseBrowser((string) $ua);
            $o = self::parseOs((string) $ua);
            $d = self::parseDevice((string) $ua);
            $browsers[$b] = ($browsers[$b] ?? 0) + 1;
            $oses[$o] = ($oses[$o] ?? 0) + 1;
            $devices[$d] = ($devices[$d] ?? 0) + 1;
        }
        arsort($browsers);
        arsort($oses);
        arsort($devices);

        $mapToArray = static function (array $map): array {
            $out = [];
            foreach ($map as $name => $count) {
                $out[] = ['name' => $name, 'count' => $count];
            }
            return $out;
        };

        return response()->json([
            'data' => [
                'total_visits' => $total,
                'unique_visitors' => $uniqueVisitors,
                'period_days' => $days,
                'bounce_rate' => $total > 0 ? round((1 - min($uniqueVisitors, $total) / max($total, 1)) * 100, 1) : 0,
                'pageviews' => $pageviews,
                'visits_by_day' => $byDay,
                'data_source' => $dataSource,
                'top_pages' => $topPaths,
                'recent_visits' => $recent,
                'browsers' => $mapToArray($browsers),
                'os' => $mapToArray($oses),
                'devices' => $mapToArray($devices),
            ],
        ]);
    }

    private static function parseBrowser(string $ua): string
    {
        if (preg_match('/Edg[e\/]/i', $ua)) {
            return 'Edge';
        }
        if (preg_match('/OPR|Opera/i', $ua)) {
            return 'Opera';
        }
        if (preg_match('/Chrome/i', $ua) && ! preg_match('/Edg/i', $ua)) {
            return 'Chrome';
        }
        if (preg_match('/Firefox/i', $ua)) {
            return 'Firefox';
        }
        if (preg_match('/Safari/i', $ua) && ! preg_match('/Chrome/i', $ua)) {
            return 'Safari';
        }
        if (preg_match('/MSIE|Trident/i', $ua)) {
            return 'IE';
        }

        return 'Other';
    }

    private static function parseOs(string $ua): string
    {
        if (preg_match('/Windows/i', $ua)) {
            return 'Windows';
        }
        if (preg_match('/Macintosh|Mac OS/i', $ua)) {
            return 'macOS';
        }
        if (preg_match('/Android/i', $ua)) {
            return 'Android';
        }
        if (preg_match('/iPhone|iPad|iPod/i', $ua)) {
            return 'iOS';
        }
        if (preg_match('/Linux/i', $ua)) {
            return 'Linux';
        }

        return 'Other';
    }

    private static function parseDevice(string $ua): string
    {
        if (preg_match('/Mobile|Android.*Mobile|iPhone/i', $ua)) {
            return 'Mobile';
        }
        if (preg_match('/iPad|Android(?!.*Mobile)|Tablet/i', $ua)) {
            return 'Tablet';
        }

        return 'Desktop';
    }
}
