<?php

namespace Modules\Hrm\Http\Controllers;

use App\Http\Controllers\Api\PaginatesApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmOnboarding;
use Modules\Hrm\Entities\HrmOnboardingTask;
use Modules\Hrm\Entities\HrmOnboardingTemplate;
use Modules\Hrm\Entities\HrmOnboardingTemplateItem;
use Modules\Hrm\Services\HrmOnboardingService;
use Modules\Hrm\Support\HrmAccess;

class OnboardingController extends Controller
{
    use PaginatesApi;

    public function templatesIndex(Request $request): JsonResponse
    {
        return $this->paginatedResponse(
            HrmOnboardingTemplate::query()->with('items')->orderBy('name')->paginate($this->perPage($request))
        );
    }

    public function templatesStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'items' => 'nullable|array',
            'items.*.title' => 'required_with:items|string|max:200',
            'items.*.description' => 'nullable|string',
            'items.*.document_category' => 'nullable|string|max:40',
            'items.*.sort_order' => 'nullable|integer|min:0',
            'items.*.required' => 'nullable|boolean',
        ]);
        $template = HrmOnboardingTemplate::query()->create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
        foreach ($data['items'] ?? [] as $i => $item) {
            HrmOnboardingTemplateItem::query()->create([
                'template_id' => $template->id,
                'title' => $item['title'],
                'description' => $item['description'] ?? null,
                'document_category' => $item['document_category'] ?? null,
                'sort_order' => $item['sort_order'] ?? $i,
                'required' => $item['required'] ?? true,
            ]);
        }

        return response()->json(['data' => $template->load('items'), 'message' => 'Template saved'], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $q = HrmOnboarding::query()->with(['employee', 'tasks'])->orderByDesc('id');
        if (! HrmAccess::seesAllStaff()) {
            $q->where('employee_id', HrmAccess::employee()?->id ?? 0);
        } elseif ($request->filled('employee_id')) {
            $q->where('employee_id', $request->integer('employee_id'));
        }

        return $this->paginatedResponse($q->paginate($this->perPage($request)));
    }

    public function start(Request $request, HrmOnboardingService $service): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:hrm_employees,id',
            'template_id' => 'required|exists:hrm_onboarding_templates,id',
        ]);
        $employee = HrmEmployee::query()->findOrFail($data['employee_id']);
        $row = $service->start($employee, (int) $data['template_id']);

        return response()->json(['data' => $row, 'message' => 'Onboarding started'], 201);
    }

    public function completeTask(HrmOnboardingTask $onboardingTask, HrmOnboardingService $service): JsonResponse
    {
        $onboarding = $onboardingTask->onboarding;
        if (! HrmAccess::canManageStaff()) {
            abort_unless(HrmAccess::employee()?->id === $onboarding->employee_id, 403);
        }
        $onboardingTask->update(['status' => 'done', 'completed_at' => now()]);

        return response()->json(['data' => $service->refreshProgress($onboarding)->load('tasks')]);
    }
}
