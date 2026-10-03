<?php

namespace Modules\Crm\Http\Controllers;

use App\Support\MutationAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Entities\CoreAutomationRule;
use Modules\Crm\Entities\CrmMessage;
use Modules\Crm\Entities\CrmMessageTemplate;
use Modules\Crm\Entities\CrmScoreRule;
use Modules\Crm\Entities\CrmSequence;
use Modules\Crm\Entities\CrmSequenceEnrollment;
use Modules\Crm\Entities\CrmSequenceStep;
use Modules\Crm\Services\CrmOutboundMessenger;
use Modules\Crm\Services\CrmSequenceRunner;
use Modules\Crm\Services\LeadScoringService;

class OutreachController extends Controller
{
    public function templates(Request $request): JsonResponse
    {
        $query = CrmMessageTemplate::query()->orderBy('name');
        if ($request->filled('channel')) {
            $query->where('channel', $request->string('channel'));
        }

        return response()->json(['data' => $query->get()]);
    }

    public function storeTemplate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'channel' => 'required|string|in:email,sms',
            'name' => 'required|string|max:160',
            'subject' => 'nullable|string|max:255',
            'body' => 'required|string',
            'is_active' => 'nullable|boolean',
        ]);
        $data['created_by'] = $request->user()->id;
        $row = CrmMessageTemplate::query()->create($data);
        MutationAudit::record($request->user()->id, 'crm', 'template.create', CrmMessageTemplate::class, $row->id, ['name' => $row->name]);

        return response()->json(['data' => $row], 201);
    }

    public function updateTemplate(Request $request, int $id): JsonResponse
    {
        $row = CrmMessageTemplate::query()->findOrFail($id);
        $data = $request->validate([
            'name' => 'sometimes|string|max:160',
            'subject' => 'nullable|string|max:255',
            'body' => 'sometimes|string',
            'is_active' => 'nullable|boolean',
        ]);
        $row->update($data);

        return response()->json(['data' => $row->fresh()]);
    }

    public function destroyTemplate(int $id): JsonResponse
    {
        CrmMessageTemplate::query()->findOrFail($id)->delete();

        return response()->json([], 204);
    }

    public function messages(Request $request): JsonResponse
    {
        $query = CrmMessage::query()->orderByDesc('id');
        if ($request->filled('related_type')) {
            $query->where('related_type', $request->string('related_type'))
                ->where('related_id', $request->integer('related_id'));
        }

        return response()->json(['data' => $query->limit(100)->get()]);
    }

    public function send(Request $request, CrmOutboundMessenger $messenger): JsonResponse
    {
        $data = $request->validate([
            'channel' => 'required|string|in:email,sms',
            'related_type' => 'required|string|in:account,contact,lead',
            'related_id' => 'required|integer|min:1',
            'to' => 'nullable|string|max:191',
            'subject' => 'nullable|string|max:255',
            'body' => 'nullable|string',
            'template_id' => 'nullable|integer|exists:crm_message_templates,id',
        ]);

        if (! empty($data['template_id']) && empty($data['body'])) {
            $template = CrmMessageTemplate::query()->findOrFail($data['template_id']);
            $message = $messenger->sendTemplate($template, $data['related_type'], (int) $data['related_id'], $request->user()->id);
        } else {
            $context = $messenger->contextFor($data['related_type'], (int) $data['related_id']);
            $to = $data['to'] ?? ($data['channel'] === 'sms' ? ($context['mobile'] ?? '') : ($context['email'] ?? ''));
            abort_if($to === '' || empty($data['body']), 422, 'Recipient and body are required');
            $message = $messenger->send(
                $data['channel'],
                $data['related_type'],
                (int) $data['related_id'],
                $to,
                $data['body'],
                $data['subject'] ?? null,
                $data['template_id'] ?? null,
                $request->user()->id,
                $context,
            );
        }
        MutationAudit::record($request->user()->id, 'crm', 'message.send', CrmMessage::class, $message->id, [
            'channel' => $message->channel,
            'status' => $message->status,
        ]);

        return response()->json(['data' => $message], 201);
    }

    public function sequences(): JsonResponse
    {
        return response()->json(['data' => CrmSequence::query()->with('steps')->orderBy('name')->get()]);
    }

    public function storeSequence(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:160',
            'channel' => 'required|string|in:email,sms',
            'steps' => 'required|array|min:1',
            'steps.*.template_id' => 'required|integer|exists:crm_message_templates,id',
            'steps.*.delay_days' => 'nullable|integer|min:0|max:365',
        ]);
        $sequence = CrmSequence::query()->create([
            'name' => $data['name'],
            'channel' => $data['channel'],
            'is_active' => true,
            'created_by' => $request->user()->id,
        ]);
        foreach (array_values($data['steps']) as $index => $step) {
            CrmSequenceStep::query()->create([
                'sequence_id' => $sequence->id,
                'template_id' => $step['template_id'],
                'delay_days' => $step['delay_days'] ?? 0,
                'sort_order' => $index,
            ]);
        }
        MutationAudit::record($request->user()->id, 'crm', 'sequence.create', CrmSequence::class, $sequence->id, ['name' => $sequence->name]);

        return response()->json(['data' => $sequence->load('steps')], 201);
    }

    public function enroll(Request $request, int $id): JsonResponse
    {
        $sequence = CrmSequence::query()->with('steps')->findOrFail($id);
        $data = $request->validate([
            'related_type' => 'required|string|in:account,contact,lead',
            'related_id' => 'required|integer|min:1',
        ]);
        $first = $sequence->steps->first();
        abort_if(! $first, 422, 'Sequence has no steps');
        $enrollment = CrmSequenceEnrollment::query()->create([
            'sequence_id' => $sequence->id,
            'related_type' => $data['related_type'],
            'related_id' => $data['related_id'],
            'step_index' => 0,
            'next_run_at' => now()->addDays((int) $first->delay_days),
            'status' => 'active',
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $enrollment], 201);
    }

    public function runDue(CrmSequenceRunner $runner): JsonResponse
    {
        return response()->json(['data' => ['sent' => $runner->runDue()]]);
    }

    public function scoreRules(): JsonResponse
    {
        return response()->json(['data' => CrmScoreRule::query()->orderBy('sort_order')->orderBy('id')->get()]);
    }

    public function storeScoreRule(Request $request): JsonResponse
    {
        $data = $this->scorePayload($request);
        $row = CrmScoreRule::query()->create($data);

        return response()->json(['data' => $row], 201);
    }

    public function updateScoreRule(Request $request, int $id): JsonResponse
    {
        $row = CrmScoreRule::query()->findOrFail($id);
        $row->update($this->scorePayload($request, partial: true));

        return response()->json(['data' => $row->fresh()]);
    }

    public function destroyScoreRule(int $id): JsonResponse
    {
        CrmScoreRule::query()->findOrFail($id)->delete();

        return response()->json([], 204);
    }

    public function recompute(LeadScoringService $scoring): JsonResponse
    {
        return response()->json(['data' => ['updated' => $scoring->recomputeAll()]]);
    }

    public function automationRules(): JsonResponse
    {
        $rows = CoreAutomationRule::query()
            ->where('trigger', 'like', 'crm.%')
            ->orderBy('priority')
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function storeAutomation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:160',
            'trigger' => 'required|string|in:crm.deal.stage_changed,crm.lead.status_changed',
            'conditions' => 'nullable|array',
            'actions' => 'required|array|min:1',
            'actions.*.type' => 'required|string|in:notify,create-task,webhook,send-sms',
            'actions.*.payload' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);
        $rule = CoreAutomationRule::query()->create([
            'name' => $data['name'],
            'trigger' => $data['trigger'],
            'conditions' => $data['conditions'] ?? [],
            'actions' => $data['actions'],
            'is_active' => $data['is_active'] ?? true,
            'priority' => 100,
            'created_by' => $request->user()->id,
        ]);
        MutationAudit::record($request->user()->id, 'crm', 'automation.create', CoreAutomationRule::class, $rule->id, [
            'trigger' => $rule->trigger,
        ]);

        return response()->json(['data' => $rule], 201);
    }

    public function destroyAutomation(int $id): JsonResponse
    {
        $rule = CoreAutomationRule::query()->where('trigger', 'like', 'crm.%')->findOrFail($id);
        $rule->delete();

        return response()->json([], 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function scorePayload(Request $request, bool $partial = false): array
    {
        $rules = [
            'name' => ($partial ? 'sometimes' : 'required').'|string|max:120',
            'kind' => ($partial ? 'sometimes' : 'required').'|string|in:field,activity',
            'target' => ($partial ? 'sometimes' : 'required').'|string|max:80',
            'operator' => 'nullable|string|in:present,eq,gte,contains',
            'match_value' => 'nullable|string|max:191',
            'weight' => ($partial ? 'sometimes' : 'required').'|integer|min:-50|max:100',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ];

        return $request->validate($rules);
    }
}
