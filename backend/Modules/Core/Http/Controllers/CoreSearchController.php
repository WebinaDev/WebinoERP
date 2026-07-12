<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Crm\Entities\CrmLead;
use Modules\Projects\Entities\Contract;
use Modules\Projects\Entities\ProjectTask;

class CoreSearchController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $request->validate(['q' => 'required|string|min:2', 'type' => 'nullable|string|in:lead,task,contract,all']);
        $q = '%'.$request->string('q').'%';
        $type = $request->input('type', 'all');
        $out = [];

        if ($type === 'all' || $type === 'lead') {
            $out['leads'] = CrmLead::query()
                ->where(function ($w) use ($q) {
                    $w->where('topic', 'like', $q)->orWhere('email', 'like', $q)->orWhere('mobile', 'like', $q);
                })
                ->orderByDesc('id')
                ->limit(20)
                ->get();
        }
        if ($type === 'all' || $type === 'task') {
            $out['tasks'] = ProjectTask::query()
                ->where('title', 'like', $q)
                ->orderByDesc('id')
                ->limit(20)
                ->get();
        }
        if ($type === 'all' || $type === 'contract') {
            $out['contracts'] = Contract::query()
                ->where('title', 'like', $q)
                ->orderByDesc('id')
                ->limit(20)
                ->get();
        }

        return response()->json(['data' => $out]);
    }
}
