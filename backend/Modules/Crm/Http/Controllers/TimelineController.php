<?php

namespace Modules\Crm\Http\Controllers;

use App\Support\MutationAudit;
use App\Support\StaffNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Crm\Entities\CrmAccount;
use Modules\Crm\Entities\CrmActivity;
use Modules\Crm\Entities\CrmDeal;
use Modules\Crm\Services\LeadScoringService;
use Modules\Crm\Support\CrmRelation;

class TimelineController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'account_id' => 'nullable|integer|exists:crm_accounts,id',
            'deal_id' => 'nullable|integer|exists:crm_deals,id',
        ]);
        abort_unless($request->filled('account_id') || $request->filled('deal_id'), 422);

        $query = CrmActivity::query()->orderByDesc('created_at');
        if ($request->filled('deal_id')) {
            $query->where('related_id', $request->integer('deal_id'))
                ->whereIn('related_model', CrmRelation::storedNames('deal'));
        } else {
            $accountId = $request->integer('account_id');
            $dealIds = CrmDeal::query()->where('account_id', $accountId)->pluck('id');
            $contactIds = CrmAccount::query()->find($accountId)?->contacts()->pluck('id') ?? collect();
            $query->where(function ($w) use ($accountId, $dealIds, $contactIds) {
                $w->where(function ($a) use ($accountId) {
                    $a->where('related_id', $accountId)->whereIn('related_model', CrmRelation::storedNames('account'));
                });
                if ($dealIds->isNotEmpty()) {
                    $w->orWhere(function ($d) use ($dealIds) {
                        $d->whereIn('related_id', $dealIds)->whereIn('related_model', CrmRelation::storedNames('deal'));
                    });
                }
                if ($contactIds->isNotEmpty()) {
                    $w->orWhere(function ($c) use ($contactIds) {
                        $c->whereIn('related_id', $contactIds)->whereIn('related_model', CrmRelation::storedNames('contact'));
                    });
                }
            });
        }

        return response()->json(['data' => $query->limit(100)->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => 'required|string|in:call,email,note,meeting,sms',
            'subject' => 'required|string|max:255',
            'description' => 'nullable|string',
            'related_type' => 'required|string|in:account,deal,contact,lead',
            'related_id' => 'required|integer|min:1',
            'remind_at' => 'nullable|date',
            'due_date' => 'nullable|date',
            'scheduled_at' => 'nullable|date',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        $activity = CrmActivity::query()->create([
            'type' => $data['type'],
            'subject' => $data['subject'],
            'description' => $data['description'] ?? null,
            'related_model' => CrmRelation::classFor($data['related_type']),
            'related_id' => $data['related_id'],
            'remind_at' => $data['remind_at'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'assigned_to' => $data['assigned_to'] ?? $request->user()->id,
            'created_by' => $request->user()->id,
        ]);
        MutationAudit::record($request->user()->id, 'crm', 'activity.create', CrmActivity::class, $activity->id, [
            'type' => $activity->type,
            'related_type' => $data['related_type'],
        ]);

        return response()->json(['data' => $activity], 201);
    }

    public function complete(Request $request, int $id, LeadScoringService $scoring): JsonResponse
    {
        $activity = CrmActivity::query()->findOrFail($id);
        $activity->completed_at = now();
        $activity->save();
        if (in_array($activity->related_model, CrmRelation::storedNames('lead'), true)) {
            $lead = \Modules\Crm\Entities\CrmLead::query()->find($activity->related_id);
            if ($lead) {
                $scoring->applyAndSave($lead);
            }
        }
        MutationAudit::record($request->user()->id, 'crm', 'activity.complete', CrmActivity::class, $activity->id, []);

        return response()->json(['data' => $activity]);
    }

    public function reminders(Request $request): JsonResponse
    {
        $rows = CrmActivity::query()
            ->whereNotNull('remind_at')
            ->whereNull('reminded_at')
            ->whereNull('completed_at')
            ->orderBy('remind_at')
            ->limit(50)
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function dispatchDue(): int
    {
        $count = 0;
        CrmActivity::query()
            ->whereNotNull('remind_at')
            ->whereNull('reminded_at')
            ->where('remind_at', '<=', now())
            ->orderBy('id')
            ->each(function (CrmActivity $activity) use (&$count) {
                $userId = (int) ($activity->assigned_to ?: $activity->created_by);
                StaffNotifier::notify($userId, 'crm.reminder', $activity->subject, (string) ($activity->description ?? ''), [
                    'activity_id' => $activity->id,
                ]);
                $activity->update(['reminded_at' => now()]);
                $count++;
            });

        return $count;
    }
}
