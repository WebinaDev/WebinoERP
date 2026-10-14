<?php

namespace Modules\Marketing\Http\Controllers\Builder;

use Modules\Marketing\Services\Builder\BuilderSite;

use Modules\Marketing\Http\Controllers\Controller;
use Modules\Marketing\Entities\BuilderRolePolicy;
use Modules\Marketing\Services\Builder\BuilderAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BuilderRolesController extends Controller
{
    /** Editable roles (admin is always full access). */
    private const ROLES = ['team_member', 'sales_consultant', 'crm_specialist', 'project_manager', 'finance_manager', 'hr_manager'];

    public function show(Request $request): JsonResponse
    {
        $rows = BuilderRolePolicy::query()->where('tenant_id', BuilderSite::ID)->get()->keyBy('role');

        return response()->json(['data' => [
            'me' => BuilderAccess::abilities($request->user()),
            'can_manage' => $this->canManage($request),
            'roles' => collect(self::ROLES)->map(fn (string $role) => [
                'role' => $role,
                'can_edit' => (bool) ($rows->get($role)?->can_edit ?? true),
                'can_publish' => (bool) ($rows->get($role)?->can_publish ?? true),
                'can_design' => (bool) ($rows->get($role)?->can_design ?? true),
            ])->values(),
        ]]);
    }

    public function update(Request $request): JsonResponse
    {
        abort_unless($this->canManage($request), 403);
        $data = $request->validate([
            'roles' => 'required|array|max:20',
            'roles.*.role' => 'required|in:'.implode(',', self::ROLES),
            'roles.*.can_edit' => 'required|boolean',
            'roles.*.can_publish' => 'required|boolean',
            'roles.*.can_design' => 'required|boolean',
        ]);
        foreach ($data['roles'] as $row) {
            BuilderRolePolicy::query()->updateOrCreate(
                ['tenant_id' => BuilderSite::ID, 'role' => $row['role']],
                ['can_edit' => $row['can_edit'], 'can_publish' => $row['can_publish'], 'can_design' => $row['can_design']],
            );
        }

        return $this->show($request);
    }

    private function canManage(Request $request): bool
    {
        $user = $request->user();

        return BuilderAccess::roleOf($user) === 'admin' || $user->can('rbac.manage');
    }
}
