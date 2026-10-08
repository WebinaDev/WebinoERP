<?php

namespace Modules\Hrm\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Modules\Core\Database\Seeders\RolesAndPermissionsSeeder;
use Modules\Hrm\Entities\HrmApprovalFlow;
use Modules\Hrm\Services\HrmApprovalWorkflow;
use Modules\Hrm\Support\HrmAccess;

class ApprovalFlowController extends Controller
{
    public function index(): JsonResponse
    {
        abort_unless(HrmAccess::seesAllStaff(), 403);
        $flows = HrmApprovalFlow::query()->orderBy('request_type')->get()->map(function (HrmApprovalFlow $f) {
            $steps = array_map(fn ($s) => HrmApprovalWorkflow::normalizeStep(is_array($s) ? $s : []), $f->steps ?? []);
            $ids = collect($steps)->pluck('user_ids')->flatten()->unique()->values()->all();
            $users = $ids ? User::query()->whereIn('id', $ids)->get(['id', 'name', 'email'])->keyBy('id') : collect();

            return [
                'id' => $f->id,
                'request_type' => $f->request_type,
                'name' => $f->name,
                'is_active' => $f->is_active,
                'steps' => array_map(fn ($s) => $s + [
                    'users' => array_values(array_filter(array_map(fn ($id) => $users->get($id)?->only(['id', 'name', 'email']), $s['user_ids']))),
                ], $steps),
                'updated_at' => optional($f->updated_at)->toIso8601String(),
            ];
        });

        return response()->json([
            'data' => [
                'flows' => $flows,
                'request_types' => HrmApprovalWorkflow::REQUEST_TYPES,
                'step_types' => HrmApprovalWorkflow::STEP_TYPES,
                'roles' => $this->roles(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(HrmAccess::canManageStaff(), 403);
        $roleNames = array_column($this->roles(), 'name');
        $data = $request->validate([
            'request_type' => ['required', Rule::in(HrmApprovalWorkflow::REQUEST_TYPES)],
            'name' => 'nullable|string|max:150',
            'is_active' => 'nullable|boolean',
            'steps' => 'required|array|min:1|max:8',
            'steps.*.type' => ['required', Rule::in(HrmApprovalWorkflow::STEP_TYPES)],
            'steps.*.role' => ['nullable', 'required_if:steps.*.type,role', Rule::in($roleNames)],
            'steps.*.user_ids' => 'nullable|required_if:steps.*.type,user|array',
            'steps.*.user_ids.*' => 'integer|exists:users,id',
        ]);
        $steps = array_map(fn ($s) => HrmApprovalWorkflow::normalizeStep($s), array_values($data['steps']));
        $row = HrmApprovalFlow::query()->updateOrCreate(
            ['request_type' => $data['request_type']],
            [
                'name' => $data['name'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'steps' => $steps,
            ]
        );

        return response()->json(['data' => $row, 'message' => 'Flow saved'], 201);
    }

    public function destroy(HrmApprovalFlow $flow): JsonResponse
    {
        abort_unless(HrmAccess::canManageStaff(), 403);
        $flow->delete();

        return response()->json(['message' => 'Deleted']);
    }

    /** Search users for "specific user" steps. */
    public function users(Request $request): JsonResponse
    {
        abort_unless(HrmAccess::seesAllStaff(), 403);
        $q = User::query()->orderBy('name')->limit(30);
        $term = trim((string) $request->input('search', ''));
        if ($term !== '') {
            $q->where(function ($w) use ($term) {
                $w->where('name', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%');
            });
        }

        return response()->json(['data' => $q->get(['id', 'name', 'email'])]);
    }

    /**
     * @return list<array{name: string, label: string}>
     */
    private function roles(): array
    {
        if (! Schema::hasTable('roles')) {
            return [];
        }
        $labels = RolesAndPermissionsSeeder::LABELS;

        return \Spatie\Permission\Models\Role::query()->orderBy('name')->pluck('name')
            ->reject(fn ($n) => $n === 'client')
            ->map(fn ($n) => ['name' => (string) $n, 'label' => (string) ($labels[$n] ?? $n)])
            ->values()->all();
    }
}
