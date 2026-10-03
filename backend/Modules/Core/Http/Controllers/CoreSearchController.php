<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Crm\Entities\CrmAccount;
use Modules\Crm\Entities\CrmContact;
use Modules\Crm\Entities\CrmDeal;
use Modules\Crm\Entities\CrmLead;
use Modules\Projects\Entities\Contract;
use Modules\Projects\Entities\Project;
use Modules\Projects\Entities\ProjectTask;

class CoreSearchController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $request->validate(['q' => 'required|string|min:2', 'type' => 'nullable|string|in:lead,task,contract,account,contact,deal,project,all']);
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
        if ($type === 'all' || $type === 'account') {
            $out['accounts'] = CrmAccount::query()
                ->where(function ($w) use ($q) {
                    $w->where('name', 'like', $q)->orWhere('website', 'like', $q)->orWhere('account_code', 'like', $q);
                })
                ->orderByDesc('id')
                ->limit(20)
                ->get(['id', 'name', 'website', 'type']);
        }
        if ($type === 'all' || $type === 'contact') {
            $out['contacts'] = CrmContact::query()
                ->where(function ($w) use ($q) {
                    $w->where('first_name', 'like', $q)
                        ->orWhere('last_name', 'like', $q)
                        ->orWhere('email', 'like', $q)
                        ->orWhere('mobile', 'like', $q);
                })
                ->orderByDesc('id')
                ->limit(20)
                ->get(['id', 'account_id', 'first_name', 'last_name', 'email', 'mobile']);
        }
        if ($type === 'all' || $type === 'deal') {
            $out['deals'] = CrmDeal::query()
                ->where('name', 'like', $q)
                ->orderByDesc('id')
                ->limit(20)
                ->get(['id', 'name', 'amount', 'account_id', 'stage_id']);
        }
        if ($type === 'all' || $type === 'project') {
            $out['projects'] = Project::query()
                ->where('name', 'like', $q)
                ->orderByDesc('id')
                ->limit(20)
                ->get(['id', 'name', 'status']);
        }

        return response()->json(['data' => $out]);
    }
}
