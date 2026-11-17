<?php

namespace Modules\Authz\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Authz\Entities\AuthzAccessLog;
use Modules\Authz\Entities\AuthzDelegation;
use Modules\Authz\Entities\AuthzFieldPolicy;
use Modules\Authz\Entities\AuthzMembership;
use Modules\Authz\Entities\AuthzPermission;
use Modules\Authz\Entities\AuthzRole;
use Modules\Authz\Entities\AuthzRolePermission;
use Modules\Authz\Entities\AuthzUserPermission;
use Modules\Authz\Entities\AuthzUserRole;
use Modules\Authz\Services\AuthorizationEngine;

class AuthzController extends Controller
{
    public function me(Request $request, AuthorizationEngine $engine): JsonResponse
    {
        $user = $request->user();
        $membership = AuthzMembership::query()->where('user_id', $user->id)->where('status', 'active')->first();
        $companyId = $membership?->company_id;

        $userRoles = AuthzUserRole::query()->with('role')
            ->where('user_id', $user->id)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->get();

        $roleIds = $userRoles->pluck('role_id')->toArray();
        $rolePerms = AuthzRolePermission::query()->with('permission')
            ->whereIn('role_id', $roleIds)
            ->get();

        $directPerms = AuthzUserPermission::query()->with('permission')
            ->where('user_id', $user->id)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->get();

        $summary = [];
        foreach ($rolePerms as $rp) {
            if ($rp->permission) {
                $summary[$rp->permission->code] = [
                    'effect' => $rp->effect,
                    'scope' => $rp->scope,
                    'conditions' => $rp->conditions_json,
                ];
            }
        }
        foreach ($directPerms as $dp) {
            if ($dp->permission) {
                $summary[$dp->permission->code] = [
                    'effect' => $dp->effect,
                    'scope' => $dp->scope,
                    'conditions' => $dp->conditions_json,
                ];
            }
        }

        return response()->json([
            'data' => [
                'user_id' => $user->id,
                'company_id' => $companyId,
                'branch_id' => $membership?->branch_id,
                'roles' => $userRoles->pluck('role.code'),
                'permissions' => $summary,
            ],
        ]);
    }

    public function check(Request $request, AuthorizationEngine $engine): JsonResponse
    {
        $data = $request->validate([
            'permission' => 'required|string',
            'company_id' => 'nullable|integer',
            'branch_id' => 'nullable|integer',
            'resource_type' => 'nullable|string',
            'resource_id' => 'nullable|integer',
            'record' => 'nullable|array',
            'context' => 'nullable|array',
        ]);

        $res = $engine->authorize(array_merge($data, [
            'user' => $request->user(),
            'record' => (object) ($data['record'] ?? []),
        ]));

        return response()->json(['data' => $res]);
    }

    public function checkBulk(Request $request, AuthorizationEngine $engine): JsonResponse
    {
        $data = $request->validate([
            'permissions' => 'required|array|min:1',
            'permissions.*' => 'required|string',
            'company_id' => 'nullable|integer',
            'branch_id' => 'nullable|integer',
        ]);

        $results = [];
        foreach ($data['permissions'] as $perm) {
            $results[$perm] = $engine->authorize([
                'user' => $request->user(),
                'permission' => $perm,
                'company_id' => $data['company_id'] ?? null,
                'branch_id' => $data['branch_id'] ?? null,
            ]);
        }

        return response()->json(['data' => $results]);
    }

    public function simulate(Request $request, AuthorizationEngine $engine): JsonResponse
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'permission' => 'required|string',
            'company_id' => 'nullable|integer',
            'branch_id' => 'nullable|integer',
            'record' => 'nullable|array',
            'context' => 'nullable|array',
        ]);

        $targetUser = User::findOrFail($data['user_id']);

        $res = $engine->authorize([
            'user' => $targetUser,
            'permission' => $data['permission'],
            'company_id' => $data['company_id'] ?? null,
            'branch_id' => $data['branch_id'] ?? null,
            'record' => (object) ($data['record'] ?? []),
            'context' => $data['context'] ?? [],
        ]);

        return response()->json([
            'data' => [
                'simulation_target' => [
                    'id' => $targetUser->id,
                    'name' => $targetUser->name,
                ],
                'evaluation' => $res,
            ],
        ]);
    }

    public function permissions(Request $request): JsonResponse
    {
        $perms = AuthzPermission::query()->orderBy('module')->orderBy('resource')->orderBy('action')->get();

        // Build tree: module -> resource -> actions
        $tree = [];
        foreach ($perms as $p) {
            $tree[$p->module][$p->resource][] = $p;
        }

        return response()->json([
            'data' => [
                'list' => $perms,
                'tree' => $tree,
            ],
        ]);
    }

    public function roles(Request $request): JsonResponse
    {
        $roles = AuthzRole::query()->withCount('userRoles')->orderBy('title_fa')->get();

        return response()->json(['data' => $roles]);
    }

    public function storeRole(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => 'required|string|max:64',
            'title_fa' => 'required|string|max:160',
            'company_id' => 'nullable|exists:crm_companies,id',
            'permissions' => 'nullable|array',
            'permissions.*.permission_id' => 'required|exists:authz_permissions,id',
            'permissions.*.scope' => 'nullable|in:all,branch,own,team,custom_filter',
            'permissions.*.conditions_json' => 'nullable|array',
            'permissions.*.effect' => 'nullable|in:allow,deny,allow_with_approval',
        ]);

        $role = AuthzRole::query()->create([
            'code' => $data['code'],
            'title_fa' => $data['title_fa'],
            'company_id' => $data['company_id'] ?? null,
            'is_system' => false,
        ]);

        if (! empty($data['permissions'])) {
            foreach ($data['permissions'] as $p) {
                AuthzRolePermission::query()->create([
                    'role_id' => $role->id,
                    'permission_id' => $p['permission_id'],
                    'scope' => $p['scope'] ?? 'all',
                    'conditions_json' => $p['conditions_json'] ?? null,
                    'effect' => $p['effect'] ?? 'allow',
                ]);
            }
        }

        return response()->json(['data' => $role->load('rolePermissions.permission')], 201);
    }

    public function showRole(int $id): JsonResponse
    {
        $role = AuthzRole::query()->with(['rolePermissions.permission', 'company'])->findOrFail($id);

        return response()->json(['data' => $role]);
    }

    public function fieldPolicies(Request $request): JsonResponse
    {
        $policies = AuthzFieldPolicy::query()->get();

        return response()->json(['data' => $policies]);
    }

    public function storeFieldPolicy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'resource' => 'required|string|max:48',
            'field' => 'required|string|max:64',
            'permission_needed' => 'required|string|max:80',
            'effect' => 'required|in:hide,mask,read_only',
        ]);

        $policy = AuthzFieldPolicy::query()->updateOrCreate(
            ['resource' => $data['resource'], 'field' => $data['field']],
            ['permission_needed' => $data['permission_needed'], 'effect' => $data['effect']]
        );

        return response()->json(['data' => $policy], 201);
    }

    public function auditLogs(Request $request): JsonResponse
    {
        $query = AuthzAccessLog::query()->with('user');
        if ($request->filled('decision')) {
            $query->where('decision', $request->input('decision'));
        }
        if ($request->filled('permission_code')) {
            $query->where('permission_code', 'like', '%'.$request->input('permission_code').'%');
        }

        return response()->json(['data' => $query->orderByDesc('id')->paginate(50)]);
    }
}
