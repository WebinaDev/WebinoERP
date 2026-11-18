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
use Modules\Authz\Entities\AuthzPolicy;
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
        $membership = AuthzMembership::query()->where('user_id', $user->id)->where('status', 'active')
            ->orderByDesc('is_default_context')->orderBy('id')->first();
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
                $this->mergeSummaryPermission($summary, $rp->permission->code, $rp->effect, $rp->scope, $rp->conditions_json);
            }
        }
        foreach ($directPerms as $dp) {
            if ($dp->permission) {
                $this->mergeSummaryPermission($summary, $dp->permission->code, $dp->effect, $dp->scope, $dp->conditions_json);
            }
        }

        return response()->json([
            'data' => [
                'user_id' => $user->id,
                'company_id' => $companyId,
                'branch_id' => $membership?->branch_id,
                'roles' => $userRoles->pluck('role.code'),
                'permissions' => $summary,
                'catalog_version' => AuthzPermission::query()->max('updated_at'),
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
            // This endpoint is advisory. Protected mutations must load their record server-side
            // and call AuthorizationEngine directly; never authorize a mutation from client data.
            'record' => isset($data['record']) ? (object) $data['record'] : null,
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

        $actorMembership = $this->activeMembership($request->user(), $data['company_id'] ?? null);
        abort_unless($actorMembership, 403, 'An active company membership is required.');
        $companyMemberIds = AuthzMembership::query()->where('company_id', $actorMembership->company_id)
            ->where('status', 'active')
            ->when($actorMembership->branch_id, fn ($q) => $q->where('branch_id', $actorMembership->branch_id))
            ->pluck('user_id');
        $targetUser = User::query()->whereIn('id', $companyMemberIds)->findOrFail($data['user_id']);
        $targetMembership = AuthzMembership::query()->where('user_id', $targetUser->id)
            ->where('company_id', $actorMembership->company_id)->where('status', 'active')
            ->orderByDesc('is_default_context')->orderBy('id')->firstOrFail();

        $res = $engine->authorize([
            'user' => $targetUser,
            'permission' => $data['permission'],
            'company_id' => $data['company_id'] ?? null,
            'branch_id' => $targetMembership->branch_id,
            'record' => (object) ($data['record'] ?? []),
            'context' => $data['context'] ?? [],
            'no_log' => true,
        ]);

        AuthzAccessLog::query()->create([
            'user_id' => $request->user()->id,
            'company_id' => $actorMembership->company_id,
            'branch_id' => $actorMembership->branch_id,
            'permission_code' => $data['permission'],
            'resource_type' => 'simulation:'.$targetUser->id,
            'resource_id' => null,
            'decision' => $res['decision'],
            'reason' => 'Simulation for user '.$targetUser->id.': '.$res['reason'],
            'obligations' => $res['obligations'],
            'ip' => $request->ip(),
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
        $membership = $this->activeMembership($request->user(), $request->integer('company_id') ?: null);
        abort_unless($membership, 403, 'An active company membership is required.');
        $roles = AuthzRole::query()->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $membership->company_id))
            ->with(['rolePermissions.permission'])->withCount('userRoles')->orderBy('title_fa')->get();

        return response()->json(['data' => $roles]);
    }

    public function storeRole(Request $request): JsonResponse
    {
        $requestedCompanyId = $request->input('company_id');
        $membership = $this->activeMembership($request->user(), $requestedCompanyId ? (int) $requestedCompanyId : null);
        abort_unless($membership, 403, 'An active company membership is required.');
        $data = $request->validate([
            'code' => 'required|string|max:64',
            'title_fa' => 'required|string|max:160',
            'title_en' => 'required|string|max:160',
            'company_id' => 'nullable|exists:crm_companies,id',
            'permissions' => 'nullable|array',
            'permissions.*.permission_id' => 'required|exists:authz_permissions,id',
            'permissions.*.scope' => 'nullable|in:all,branch,own,team,warehouse_ids,custom_filter',
            'permissions.*.conditions_json' => 'nullable|array',
            'permissions.*.effect' => 'nullable|in:allow,deny,allow_with_approval',
        ]);

        $role = AuthzRole::query()->create([
            'code' => $data['code'],
            'title_fa' => $data['title_fa'],
            'title_en' => $data['title_en'],
            'company_id' => $membership->company_id,
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
        $request = request();
        $membership = $this->activeMembership($request->user(), $request->integer('company_id') ?: null);
        abort_unless($membership, 403, 'An active company membership is required.');
        $role = AuthzRole::query()->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $membership->company_id))
            ->with(['rolePermissions.permission', 'company'])->findOrFail($id);

        return response()->json(['data' => $role]);
    }

    public function updateRole(Request $request, int $id): JsonResponse
    {
        $companyId = $request->input('company_id');
        $membership = $this->activeMembership($request->user(), $companyId ? (int) $companyId : null);
        abort_unless($membership, 403, 'An active company membership is required.');
        $role = AuthzRole::query()->where('company_id', $membership->company_id)->where('is_system', false)->findOrFail($id);
        $data = $request->validate([
            'title_fa' => 'required|string|max:160',
            'title_en' => 'required|string|max:160',
            'permissions' => 'present|array',
            'permissions.*.permission_id' => 'required|exists:authz_permissions,id',
            'permissions.*.scope' => 'required|in:all,branch,own,team,warehouse_ids,custom_filter',
            'permissions.*.conditions_json' => 'nullable|array',
            'permissions.*.effect' => 'required|in:allow,deny,allow_with_approval',
        ]);
        \Illuminate\Support\Facades\DB::transaction(function () use ($role, $data): void {
            $role->update(['title_fa' => $data['title_fa'], 'title_en' => $data['title_en']]);
            $role->rolePermissions()->delete();
            foreach ($data['permissions'] as $permission) {
                $role->rolePermissions()->create([
                    'permission_id' => $permission['permission_id'], 'scope' => $permission['scope'],
                    'conditions_json' => $permission['conditions_json'] ?? null, 'effect' => $permission['effect'],
                ]);
            }
        });
        return response()->json(['data' => $role->load('rolePermissions.permission')]);
    }

    public function fieldPolicies(Request $request): JsonResponse
    {
        $policies = AuthzFieldPolicy::query()->get();

        return response()->json(['data' => $policies]);
    }

    public function policies(Request $request): JsonResponse
    {
        return response()->json(['data' => AuthzPolicy::query()->orderByDesc('priority')->get()]);
    }

    public function storePolicy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:160',
            'name_en' => 'required|string|max:160',
            'target_permission' => 'nullable|string|max:80|exists:authz_permissions,code',
            'target_resource' => 'nullable|string|max:48',
            'condition_expression' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z_][a-zA-Z0-9_]*\\s*(<=|>=|==|!=|<|>)\\s*(?:[0-9]+(?:\\.[0-9]+)?|\'[^\']*\'|"[^"]*")$/'],
            'effect' => 'required|in:allow,deny,allow_with_approval',
            'priority' => 'required|integer|min:0|max:10000',
            'is_active' => 'required|boolean',
        ]);
        $policy = AuthzPolicy::query()->updateOrCreate(['name' => $data['name']], $data);
        return response()->json(['data' => $policy], 201);
    }

    public function memberships(Request $request): JsonResponse
    {
        $companyId = $request->integer('company_id') ?: null;
        $membership = $this->activeMembership($request->user(), $companyId);
        abort_unless($membership, 403, 'An active company membership is required.');
        $rows = AuthzMembership::query()->with(['user:id,name,email'])
            ->where('company_id', $membership->company_id)
            ->when($membership->branch_id, fn ($q) => $q->where('branch_id', $membership->branch_id))
            ->orderBy('branch_id')->orderBy('id')->get();
        $roles = AuthzUserRole::query()->with('role:id,code,title_fa,title_en')
            ->where('company_id', $membership->company_id)->get()->groupBy('user_id');
        $rows->each(function ($row) use ($roles): void {
            $row->setAttribute('assigned_roles', ($roles[$row->user_id] ?? collect())->pluck('role')->filter()->values());
        });
        return response()->json(['data' => $rows]);
    }

    public function assignMembershipRole(Request $request, int $membershipId): JsonResponse
    {
        $companyId = $request->input('company_id');
        $membership = $this->activeMembership($request->user(), $companyId ? (int) $companyId : null);
        abort_unless($membership, 403, 'An active company membership is required.');
        $targetQuery = AuthzMembership::query()->where('company_id', $membership->company_id)->where('status', 'active');
        if ($membership->branch_id !== null) $targetQuery->where('branch_id', $membership->branch_id);
        $target = $targetQuery->findOrFail($membershipId);
        $data = $request->validate([
            'role_id' => 'required|integer|exists:authz_roles,id',
            'scope_json' => 'nullable|array',
        ]);
        $role = AuthzRole::query()->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $membership->company_id))->findOrFail($data['role_id']);
        abort_if($role->code === 'super_admin', 403, 'The holding super-admin template cannot be assigned from a company context.');
        $assignment = AuthzUserRole::query()->updateOrCreate(
            ['user_id' => $target->user_id, 'company_id' => $membership->company_id, 'role_id' => $role->id],
            ['scope_json' => $data['scope_json'] ?? null]
        );
        return response()->json(['data' => $assignment->load('role')], 201);
    }

    public function removeMembershipRole(Request $request, int $membershipId, int $roleId): JsonResponse
    {
        $membership = $this->activeMembership($request->user(), $request->integer('company_id') ?: null);
        abort_unless($membership, 403, 'An active company membership is required.');
        $targetQuery = AuthzMembership::query()->where('company_id', $membership->company_id);
        if ($membership->branch_id !== null) $targetQuery->where('branch_id', $membership->branch_id);
        $target = $targetQuery->findOrFail($membershipId);
        AuthzUserRole::query()->where('user_id', $target->user_id)->where('company_id', $membership->company_id)->where('role_id', $roleId)->delete();
        return response()->json(['data' => ['removed' => true]]);
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
        $membership = $this->activeMembership($request->user(), $request->integer('company_id') ?: null);
        abort_unless($membership, 403, 'An active company membership is required.');
        $query = AuthzAccessLog::query()->with('user')->where('company_id', $membership->company_id);
        if ($membership->branch_id !== null) $query->where('branch_id', $membership->branch_id);
        if ($request->filled('decision')) {
            $query->where('decision', $request->input('decision'));
        }
        if ($request->filled('permission_code')) {
            $query->where('permission_code', 'like', '%'.$request->input('permission_code').'%');
        }

        return response()->json(['data' => $query->orderByDesc('id')->paginate(50)]);
    }

    private function activeMembership(User $user, ?int $companyId): ?AuthzMembership
    {
        $query = AuthzMembership::query()->where('user_id', $user->id)->where('status', 'active');
        if ($companyId) $query->where('company_id', $companyId);
        else $query->orderByDesc('is_default_context')->orderBy('id');
        return $query->first();
    }

    private function mergeSummaryPermission(array &$summary, string $code, string $effect, ?string $scope, ?array $conditions): void
    {
        $current = $summary[$code] ?? null;
        $deny = $effect === 'deny' || ($current['effect'] ?? null) === 'deny';
        $scopeOrder = ['all' => 0, 'branch' => 1, 'team' => 2, 'own' => 3, 'warehouse_ids' => 4, 'custom_filter' => 5];
        $oldScope = $current['scope'] ?? 'all';
        $newScope = $scope ?? 'all';
        $effectiveScope = ($scopeOrder[$newScope] ?? 99) > ($scopeOrder[$oldScope] ?? 99) ? $newScope : $oldScope;
        $summary[$code] = [
            'effect' => $deny ? 'deny' : (($effect === 'allow_with_approval' || ($current['effect'] ?? null) === 'allow_with_approval') ? 'allow_with_approval' : 'allow'),
            'scope' => $effectiveScope,
            'conditions' => array_merge($current['conditions'] ?? [], $conditions ?? []),
        ];
    }
}
