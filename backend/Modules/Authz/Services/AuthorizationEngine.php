<?php

namespace Modules\Authz\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Authz\Entities\AuthzAccessLog;
use Modules\Authz\Entities\AuthzDelegation;
use Modules\Authz\Entities\AuthzFieldPolicy;
use Modules\Authz\Entities\AuthzMembership;
use Modules\Authz\Entities\AuthzPermission;
use Modules\Authz\Entities\AuthzPolicy;
use Modules\Authz\Entities\AuthzRolePermission;
use Modules\Authz\Entities\AuthzUserPermission;
use Modules\Authz\Entities\AuthzUserRole;

class AuthorizationEngine
{
    /**
     * Unified evaluation: RBAC + Policy + Scope + Field + Obligations
     *
     * @param  array{
     *   user: User,
     *   permission: string,
     *   company_id?: int|null,
     *   branch_id?: int|null,
     *   resource_type?: string|null,
     *   resource_id?: int|null,
     *   record?: object|array|null,
     *   context?: array<string, mixed>,
     *   ip?: string|null
     * }  $request
     * @return array{
     *   allowed: bool,
     *   decision: string,
     *   reason: string,
     *   obligations: array<string, mixed>,
     *   effective_scope: string
     * }
     */
    public function authorize(array $request): array
    {
        $user = $request['user'];
        $permissionCode = $request['permission'];
        $companyId = $request['company_id'] ?? null;
        $branchId = $request['branch_id'] ?? null;
        $record = $request['record'] ?? null;
        $context = $request['context'] ?? [];
        $ip = $request['ip'] ?? request()?->ip();

        // 1. System manager bypass
        if ($user->hasRole('system_manager')) {
            return $this->logAndReturn($user, $companyId, $branchId, $permissionCode, $request['resource_type'] ?? null, $request['resource_id'] ?? null, 'allow', 'System manager superuser bypass', [], 'all', $ip);
        }

        // 2. Resolve effective company context
        if (! $companyId) {
            $membership = AuthzMembership::query()->where('user_id', $user->id)->where('status', 'active')->first();
            $companyId = $membership?->company_id;
        }

        // 3. Collect active delegations for user
        $effectiveUserIds = [$user->id];
        $delegations = AuthzDelegation::query()
            ->where('delegate_id', $user->id)
            ->where('is_active', true)
            ->whereDate('start_date', '<=', now()->toDateString())
            ->whereDate('end_date', '>=', now()->toDateString())
            ->pluck('delegator_id')
            ->toArray();
        if (! empty($delegations)) {
            $effectiveUserIds = array_values(array_unique(array_merge($effectiveUserIds, $delegations)));
        }

        // 4. Find atomic permission catalog entry
        $perm = AuthzPermission::query()->where('code', $permissionCode)->first();

        // 5. Check direct user exceptions (deny wins first)
        $directDeny = AuthzUserPermission::query()
            ->whereIn('user_id', $effectiveUserIds)
            ->where('permission_id', $perm?->id)
            ->where('effect', 'deny')
            ->first();
        if ($directDeny) {
            return $this->logAndReturn($user, $companyId, $branchId, $permissionCode, $request['resource_type'] ?? null, $request['resource_id'] ?? null, 'deny', 'Direct user denial rule', [], 'none', $ip);
        }

        // 6. Gather active roles for company
        $userRoles = AuthzUserRole::query()
            ->whereIn('user_id', $effectiveUserIds)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->pluck('role_id')
            ->toArray();

        // 7. Check role permissions
        $rolePerms = AuthzRolePermission::query()
            ->whereIn('role_id', $userRoles)
            ->where('permission_id', $perm?->id)
            ->get();

        // Deny in roles always wins over allow
        if ($rolePerms->where('effect', 'deny')->isNotEmpty()) {
            return $this->logAndReturn($user, $companyId, $branchId, $permissionCode, $request['resource_type'] ?? null, $request['resource_id'] ?? null, 'deny', 'Role-level explicit deny', [], 'none', $ip);
        }

        // Check if there is an allow or allow_with_approval
        $effectiveRule = $rolePerms->whereIn('effect', ['allow', 'allow_with_approval'])->first();
        $directAllow = AuthzUserPermission::query()
            ->whereIn('user_id', $effectiveUserIds)
            ->where('permission_id', $perm?->id)
            ->where('effect', 'allow')
            ->first();

        if (! $effectiveRule && ! $directAllow) {
            return $this->logAndReturn($user, $companyId, $branchId, $permissionCode, $request['resource_type'] ?? null, $request['resource_id'] ?? null, 'deny', 'No grant matching permission code', [], 'none', $ip);
        }

        $effectiveScope = $directAllow?->scope ?? $effectiveRule?->scope ?? 'all';
        $conditions = $directAllow?->conditions_json ?? $effectiveRule?->conditions_json ?? [];

        // 8. Scope validation against record (own, branch, team, etc.)
        if ($record && ! $this->satisfiesScope($user, $record, $effectiveScope, $branchId)) {
            return $this->logAndReturn($user, $companyId, $branchId, $permissionCode, $request['resource_type'] ?? null, $request['resource_id'] ?? null, 'deny', "Record out of user scope ({$effectiveScope})", [], $effectiveScope, $ip);
        }

        // 9. ABAC conditions check (e.g., max_amount)
        $amount = (float) ($context['amount'] ?? ($record->total ?? $record->amount ?? 0));
        if (isset($conditions['max_amount']) && $amount > (float) $conditions['max_amount']) {
            if (($effectiveRule?->effect ?? '') === 'allow_with_approval') {
                return $this->logAndReturn($user, $companyId, $branchId, $permissionCode, $request['resource_type'] ?? null, $request['resource_id'] ?? null, 'allow_with_approval', "Amount {$amount} exceeds threshold; routed to workflow", ['requires_workflow' => true], $effectiveScope, $ip);
            }

            return $this->logAndReturn($user, $companyId, $branchId, $permissionCode, $request['resource_type'] ?? null, $request['resource_id'] ?? null, 'deny', "Amount {$amount} exceeds authorized limit ({$conditions['max_amount']})", [], $effectiveScope, $ip);
        }

        // 10. Obligations (MFA, reason requirement)
        $obligations = [];
        if ($perm?->requires_mfa) {
            $obligations['requires_mfa'] = true;
        }
        if ($perm?->requires_reason) {
            $obligations['requires_reason'] = true;
        }

        $decision = ($effectiveRule?->effect === 'allow_with_approval') ? 'allow_with_approval' : 'allow';

        return $this->logAndReturn($user, $companyId, $branchId, $permissionCode, $request['resource_type'] ?? null, $request['resource_id'] ?? null, $decision, 'Authorized via RBAC + Scope + Policy', $obligations, $effectiveScope, $ip);
    }

    public function satisfiesScope(User $user, object|array $record, string $scope, ?int $activeBranchId = null): bool
    {
        if ($scope === 'all') {
            return true;
        }

        $recBranchId = is_array($record) ? ($record['branch_id'] ?? null) : ($record->branch_id ?? null);
        $recOwnerId = is_array($record) ? ($record['created_by'] ?? $record['user_id'] ?? null) : ($record->created_by ?? $record->user_id ?? null);

        if ($scope === 'branch') {
            return $activeBranchId !== null && (int) $recBranchId === (int) $activeBranchId;
        }

        if ($scope === 'own') {
            return $recOwnerId !== null && (int) $recOwnerId === (int) $user->id;
        }

        return true;
    }

    public function applyScopeToQuery(Builder $query, User $user, string $scope, ?int $activeBranchId = null): Builder
    {
        if ($user->hasRole('system_manager') || $scope === 'all') {
            return $query;
        }

        if ($scope === 'branch' && $activeBranchId) {
            return $query->where('branch_id', $activeBranchId);
        }

        if ($scope === 'own') {
            return $query->where('created_by', $user->id);
        }

        return $query;
    }

    public function filterFields(User $user, string $resource, array $data): array
    {
        if ($user->hasRole('system_manager')) {
            return $data;
        }

        $policies = AuthzFieldPolicy::query()->where('resource', $resource)->get();
        foreach ($policies as $policy) {
            if (! array_key_exists($policy->field, $data)) {
                continue;
            }

            $hasPerm = $this->authorize([
                'user' => $user,
                'permission' => $policy->permission_needed,
            ])['allowed'];

            if (! $hasPerm) {
                if ($policy->effect === 'hide') {
                    unset($data[$policy->field]);
                } elseif ($policy->effect === 'mask') {
                    $val = (string) $data[$policy->field];
                    $data[$policy->field] = strlen($val) > 4 ? str_repeat('*', strlen($val) - 4).substr($val, -4) : '****';
                }
            }
        }

        return $data;
    }

    private function logAndReturn(User $user, ?int $companyId, ?int $branchId, string $perm, ?string $resType, ?int $resId, string $decision, string $reason, array $obligations, string $scope, ?string $ip): array
    {
        AuthzAccessLog::query()->create([
            'user_id' => $user->id,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'permission_code' => $perm,
            'resource_type' => $resType,
            'resource_id' => $resId,
            'decision' => $decision,
            'reason' => $reason,
            'obligations' => $obligations,
            'ip' => $ip,
        ]);

        return [
            'allowed' => in_array($decision, ['allow', 'allow_with_approval'], true),
            'decision' => $decision,
            'reason' => $reason,
            'obligations' => $obligations,
            'effective_scope' => $scope,
        ];
    }
}
