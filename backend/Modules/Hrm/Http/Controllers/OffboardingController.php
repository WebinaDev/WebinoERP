<?php

namespace Modules\Hrm\Http\Controllers;

use App\Http\Controllers\Api\PaginatesApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmOffboarding;
use Modules\Hrm\Entities\HrmOffboardingTask;
use Modules\Hrm\Entities\HrmOffboardingTemplate;
use Modules\Hrm\Entities\HrmOffboardingTemplateItem;
use Modules\Hrm\Services\HrmOffboardingService;
use Modules\Hrm\Support\HrmAccess;

class OffboardingController extends Controller
{
    use PaginatesApi;

    public function templatesIndex(Request $request): JsonResponse
    {
        abort_unless(HrmAccess::seesAllStaff(), 403);

        return $this->paginatedResponse(
            HrmOffboardingTemplate::query()->with('items')->orderBy('name')->paginate($this->perPage($request))
        );
    }

    public function templatesStore(Request $request): JsonResponse
    {
        abort_unless(HrmAccess::canManageStaff(), 403);
        $data = $this->validateTemplate($request);
        $template = DB::transaction(function () use ($data) {
            $template = HrmOffboardingTemplate::query()->create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);
            $this->syncItems($template, $data['items'] ?? []);

            return $template;
        });

        return response()->json(['data' => $template->load('items'), 'message' => 'Template saved'], 201);
    }

    public function templatesUpdate(Request $request, HrmOffboardingTemplate $offboardingTemplate): JsonResponse
    {
        abort_unless(HrmAccess::canManageStaff(), 403);
        $data = $this->validateTemplate($request);
        DB::transaction(function () use ($offboardingTemplate, $data) {
            $offboardingTemplate->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);
            $offboardingTemplate->items()->delete();
            $this->syncItems($offboardingTemplate, $data['items'] ?? []);
        });

        return response()->json(['data' => $offboardingTemplate->fresh('items')]);
    }

    public function templatesDestroy(HrmOffboardingTemplate $offboardingTemplate): JsonResponse
    {
        abort_unless(HrmAccess::canManageStaff(), 403);
        $offboardingTemplate->delete();

        return response()->json(['message' => 'Deleted']);
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless(HrmAccess::seesAllStaff(), 403);
        $q = HrmOffboarding::query()->with(['employee', 'tasks', 'template'])->orderByDesc('id');
        if ($request->filled('employee_id')) {
            $q->where('employee_id', $request->integer('employee_id'));
        }
        if ($request->filled('status')) {
            $q->where('status', $request->string('status')->toString());
        }

        return $this->paginatedResponse($q->paginate($this->perPage($request)));
    }

    /** Employee portal: own offboarding cases. */
    public function mine(): JsonResponse
    {
        $employee = HrmAccess::employee();
        if (! $employee) {
            return response()->json(['data' => []]);
        }
        $rows = HrmOffboarding::query()->with(['tasks', 'template'])
            ->where('employee_id', $employee->id)
            ->where('status', '!=', 'cancelled')
            ->orderByDesc('id')->get();

        return response()->json(['data' => $rows]);
    }

    public function start(Request $request, HrmOffboardingService $service): JsonResponse
    {
        abort_unless(HrmAccess::canManageStaff(), 403);
        $data = $request->validate([
            'employee_id' => 'required|exists:hrm_employees,id',
            'template_id' => 'required|exists:hrm_offboarding_templates,id',
            'last_day' => 'nullable|date',
            'reason' => ['nullable', Rule::in(HrmOffboardingService::REASONS)],
        ]);
        $employee = HrmEmployee::query()->findOrFail($data['employee_id']);
        $open = HrmOffboarding::query()->where('employee_id', $employee->id)->where('status', 'in_progress')->exists();
        abort_if($open, 422, 'An offboarding is already in progress for this employee');
        $row = $service->start($employee, (int) $data['template_id'], $data['last_day'] ?? null, $data['reason'] ?? null);

        return response()->json(['data' => $row->load(['employee', 'tasks']), 'message' => 'Offboarding started'], 201);
    }

    public function update(Request $request, HrmOffboarding $offboarding): JsonResponse
    {
        $manager = HrmAccess::canManageStaff();
        if (! $manager) {
            abort_unless(HrmAccess::employee()?->id === $offboarding->employee_id, 403);
        }
        $rules = ['exit_interview_notes' => 'nullable|string|max:5000'];
        if ($manager) {
            $rules += [
                'last_day' => 'nullable|date',
                'reason' => ['nullable', Rule::in(HrmOffboardingService::REASONS)],
                'status' => ['nullable', Rule::in(['in_progress', 'cancelled'])],
            ];
        }
        $data = $request->validate($rules);
        $offboarding->update($data);

        return response()->json(['data' => $offboarding->fresh(['tasks', 'employee'])]);
    }

    public function destroy(HrmOffboarding $offboarding): JsonResponse
    {
        abort_unless(HrmAccess::canManageStaff(), 403);
        $offboarding->delete();

        return response()->json(['message' => 'Deleted']);
    }

    public function completeTask(Request $request, HrmOffboardingTask $offboardingTask, HrmOffboardingService $service): JsonResponse
    {
        return $this->setTaskStatus($request, $offboardingTask, $service, 'done');
    }

    public function updateTask(Request $request, HrmOffboardingTask $offboardingTask, HrmOffboardingService $service): JsonResponse
    {
        abort_unless(HrmAccess::canManageStaff(), 403);
        $status = $request->validate(['status' => ['required', Rule::in(['pending', 'done', 'skipped'])]])['status'];

        return $this->setTaskStatus($request, $offboardingTask, $service, $status);
    }

    private function setTaskStatus(Request $request, HrmOffboardingTask $task, HrmOffboardingService $service, string $status): JsonResponse
    {
        $offboarding = $task->offboarding;
        if (! HrmAccess::canManageStaff()) {
            abort_unless(HrmAccess::employee()?->id === $offboarding->employee_id, 403);
            abort_unless($task->owner === 'employee', 403);
        }
        abort_if($offboarding->status === 'cancelled', 422, 'Offboarding is cancelled');
        $data = $request->validate([
            'notes' => 'nullable|string|max:5000',
            'asset_label' => 'nullable|string|max:150',
        ]);
        $task->update([
            'status' => $status,
            'completed_at' => $status === 'pending' ? null : now(),
            'notes' => $data['notes'] ?? $task->notes,
            'asset_label' => $data['asset_label'] ?? $task->asset_label,
        ]);
        if ($task->kind === 'exit_interview' && ! empty($data['notes'])) {
            $offboarding->update(['exit_interview_notes' => $data['notes']]);
        }

        return response()->json(['data' => $service->refreshProgress($offboarding)->load(['tasks', 'employee'])]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateTemplate(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:150',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'items' => 'nullable|array|max:50',
            'items.*.title' => 'required|string|max:200',
            'items.*.description' => 'nullable|string',
            'items.*.kind' => ['nullable', Rule::in(HrmOffboardingService::KINDS)],
            'items.*.owner' => ['nullable', Rule::in(HrmOffboardingService::OWNERS)],
            'items.*.sort_order' => 'nullable|integer|min:0',
            'items.*.required' => 'nullable|boolean',
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function syncItems(HrmOffboardingTemplate $template, array $items): void
    {
        foreach (array_values($items) as $i => $item) {
            HrmOffboardingTemplateItem::query()->create([
                'template_id' => $template->id,
                'title' => $item['title'],
                'description' => $item['description'] ?? null,
                'kind' => $item['kind'] ?? 'checklist',
                'owner' => $item['owner'] ?? 'hr',
                'sort_order' => $item['sort_order'] ?? $i,
                'required' => $item['required'] ?? true,
            ]);
        }
    }
}
