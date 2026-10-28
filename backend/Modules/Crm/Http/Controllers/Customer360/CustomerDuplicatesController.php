<?php

namespace Modules\Crm\Http\Controllers\Customer360;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Crm\Services\Customer360\CustomerDuplicateService;

/** PHASE5_API.md §1 — duplicate suggestions; merge itself stays on POST /{id}/hub/merge. */
class CustomerDuplicatesController extends Hub360Base
{
    public function index(Request $r, CustomerDuplicateService $dups): JsonResponse
    {
        abort_unless($this->access->canView($r->user(), 'identity'), 403, __('Forbidden'));

        return $this->respond($r, $dups->pairs(null, $this->minScore($r)));
    }

    public function forAccount(Request $r, int $id, CustomerDuplicateService $dups): JsonResponse
    {
        $a = $this->account($r, $id, 'identity');

        return $this->respond($r, $dups->pairs($a->id, $this->minScore($r)));
    }

    public function dismiss(Request $r, CustomerDuplicateService $dups): Response
    {
        abort_unless($this->access->canManage($r->user(), 'merge'), 403, __('Forbidden'));
        $v = $r->validate(['a_id' => 'required|integer|exists:crm_accounts,id', 'b_id' => 'required|integer|exists:crm_accounts,id']);
        $dups->dismiss((int) $v['a_id'], (int) $v['b_id'], $r->user()->id);

        return response()->noContent();
    }

    private function minScore(Request $r): int
    {
        return max(0, min(100, (int) $r->query('min_score', 50)));
    }

    private function respond(Request $r, $rows): JsonResponse
    {
        [$data, $meta] = $this->page($r, $rows);

        return response()->json(['data' => $data, 'meta' => $meta + ['can' => ['merge' => $this->access->canManage($r->user(), 'merge')]]]);
    }
}
