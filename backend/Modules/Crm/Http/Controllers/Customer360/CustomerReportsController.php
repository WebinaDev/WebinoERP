<?php

namespace Modules\Crm\Http\Controllers\Customer360;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Crm\Services\Customer360\CustomerBiService;

/** PHASE5_API.md §4 — BI reports; money fields are null without billing visibility. */
class CustomerReportsController extends Hub360Base
{
    private const MONEY = ['avg_ltv', 'median_ltv', 'total_revenue', 'total', 'outstanding', 'overdue', 'revenue', 'current', 'd1_30', 'd31_60', 'd61_90', 'd90_plus'];

    public function show(Request $r, string $section, CustomerBiService $bi): JsonResponse
    {
        abort_unless($this->access->canManage($r->user(), 'bi'), 403, __('Forbidden'));
        abort_unless(in_array($section, CustomerBiService::SECTIONS, true), 404);
        $f = $r->validate(['from' => 'nullable|date', 'to' => 'nullable|date', 'segment' => 'nullable|string|max:40', 'limit' => 'nullable|integer|min:1|max:200']);
        $data = $bi->report($section, $f);

        return response()->json(['data' => $this->money($r) ? $data : $this->mask($data), 'meta' => ['section' => $section, 'filters' => $f]]);
    }

    private function mask(array $d): array
    {
        foreach ($d as $k => $v) {
            if (is_array($v)) {
                $d[$k] = $this->mask($v);
            } elseif (is_string($k) && in_array($k, self::MONEY, true)) {
                $d[$k] = null;
            }
        }

        return $d;
    }
}
