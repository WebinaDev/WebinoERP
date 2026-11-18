<?php

namespace Modules\Authz\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Modules\Authz\Entities\AuthzAccessLog;
use Modules\Authz\Entities\AuthzDelegation;
use Modules\Authz\Entities\AuthzFieldPolicy;
use Modules\Authz\Entities\AuthzMembership;
use Modules\Authz\Entities\AuthzPermission;
use Modules\Authz\Entities\AuthzPolicy;
use Modules\Authz\Entities\AuthzRolePermission;
use Modules\Authz\Entities\AuthzUserPermission;
use Modules\Authz\Entities\AuthzUserRole;
use Modules\Org\Entities\OrgBranch;
use Illuminate\Support\Facades\Schema;

/** Central authorization decision point. Module code should call this service. */
class AuthorizationEngine
{
    /** @return array{allowed:bool,decision:string,reason:string,obligations:array,effective_scope:string,source?:string} */
    public function authorize(array $request): array
    {
        $user = $request['user'] ?? null;
        $code = trim((string) ($request['permission'] ?? ''));
        $companyId = isset($request['company_id']) ? (int) $request['company_id'] : null;
        $branchId = isset($request['branch_id']) ? (int) $request['branch_id'] : null;
        $record = $request['record'] ?? null;
        $context = $request['context'] ?? [];
        $ip = $request['ip'] ?? request()?->ip();

        if (! $user instanceof User || $code === '') {
            return $this->finish($user instanceof User ? $user : null, $companyId, $branchId, $code ?: 'invalid', $request, 'deny', 'Invalid authorization subject or permission.', [], 'none', $ip);
        }

        $permission = AuthzPermission::query()->where('code', $code)->first();
        if (! $permission) {
            return $this->finish($user, $companyId, $branchId, $code, $request, 'deny', 'Permission is not in the registered catalog.', [], 'none', $ip);
        }

        // Company context is selected by the caller, but must always be backed by an active membership.
        $membershipQuery = AuthzMembership::query()->where('user_id', $user->id)->where('status', 'active');
        if ($companyId !== null) {
            $membershipQuery->where('company_id', $companyId);
            if ($branchId !== null) {
                $membershipQuery->where(fn ($q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id'));
                $membershipQuery->orderByRaw('CASE WHEN branch_id = ? THEN 0 ELSE 1 END', [$branchId]);
            } else {
                $membershipQuery->orderByDesc('is_default_context')->orderByRaw('branch_id is null desc');
            }
        } else {
            $membershipQuery->where('is_default_context', true);
        }
        $membership = $membershipQuery->orderBy('id')->first();
        if (! $membership) {
            return $this->finish($user, $companyId, $branchId, $code, $request, 'deny', 'No active membership for the selected company context.', [], 'none', $ip);
        }
        $companyId = (int) $membership->company_id;
        if ($membership->branch_id !== null) {
            if ($branchId !== null && $branchId !== (int) $membership->branch_id) {
                return $this->finish($user, $companyId, $branchId, $code, $request, 'deny', 'Selected branch is outside the user membership.', [], 'none', $ip);
            }
            $branchId = (int) $membership->branch_id;
        }
        if ($branchId !== null && ! OrgBranch::query()->where('id', $branchId)->where('company_id', $companyId)->where('is_active', true)->exists()) {
            return $this->finish($user, $companyId, $branchId, $code, $request, 'deny', 'Selected branch does not belong to the active company.', [], 'none', $ip);
        }

        $delegatedUserIds = AuthzDelegation::query()
            ->where('delegate_id', $user->id)->where('company_id', $companyId)->where('is_active', true)
            ->whereDate('start_date', '<=', now()->toDateString())->whereDate('end_date', '>=', now()->toDateString())
            ->where(fn ($q) => $q->whereNull('scope_module')->orWhere('scope_module', $permission->module))
            ->pluck('delegator_id')->map(fn ($id) => (int) $id)->all();
        $subjectIds = array_values(array_unique(array_merge([(int) $user->id], $delegatedUserIds)));

        // Delegation is additive only when the delegator is also an active member of this company.
        $delegatedMembers = AuthzMembership::query()->whereIn('user_id', $delegatedUserIds)
            ->where('company_id', $companyId)->where('status', 'active')->pluck('user_id')->map(fn ($id) => (int) $id)->all();
        $subjectIds = array_values(array_unique(array_merge([(int) $user->id], $delegatedMembers)));

        $direct = AuthzUserPermission::query()->whereIn('user_id', $subjectIds)->where('company_id', $companyId)
            ->where('permission_id', $permission->id)->get();
        $assignments = AuthzUserRole::query()->whereIn('user_id', $subjectIds)->where('company_id', $companyId)->get();
        $roleIds = $assignments->pluck('role_id');
        $roleRules = AuthzRolePermission::query()->whereIn('role_id', $roleIds)->where('permission_id', $permission->id)
            ->whereHas('role', fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $companyId))->get();
        $assignmentScopes = $assignments->keyBy('role_id')->map(fn ($assignment) => $assignment->scope_json ?? [])->all();
        foreach ($roleRules as $roleRule) {
            $assigned = $assignmentScopes[$roleRule->role_id] ?? [];
            if (isset($assigned['scope'])) {
                $rank = ['all' => 0, 'branch' => 1, 'team' => 2, 'own' => 3, 'warehouse_ids' => 4, 'custom_filter' => 5];
                $assignedScope = (string) $assigned['scope'];
                if (($rank[$assignedScope] ?? 99) > ($rank[$roleRule->scope] ?? 99)) $roleRule->scope = $assignedScope;
            }
            if (isset($assigned['conditions']) && is_array($assigned['conditions'])) {
                $roleRule->conditions_json = array_merge($roleRule->conditions_json ?? [], $assigned['conditions']);
            }
        }

        // Explicit denial is absolute, including for system roles and direct grants.
        if ($direct->contains('effect', 'deny') || $roleRules->contains('effect', 'deny')) {
            return $this->finish($user, $companyId, $branchId, $code, $request, 'deny', 'An explicit deny rule takes precedence over every grant.', [], 'none', $ip);
        }

        $directAllows = $direct->where('effect', 'allow');
        $roleAllows = $roleRules->whereIn('effect', ['allow', 'allow_with_approval']);
        if ($directAllows->isEmpty() && $roleAllows->isEmpty()) {
            return $this->finish($user, $companyId, $branchId, $code, $request, 'deny', 'No direct or role grant matches this permission.', [], 'none', $ip);
        }

        $grants = $directAllows->concat($roleAllows);
        $scopeRank = ['all' => 0, 'branch' => 1, 'team' => 2, 'own' => 3, 'warehouse_ids' => 4, 'custom_filter' => 5];
        $grant = $grants->sortBy(fn ($item) => $scopeRank[$item->scope ?? 'custom_filter'] ?? 99)->first();
        $scope = (string) ($grant->scope ?? 'all');
        $approval = $roleAllows->contains('effect', 'allow_with_approval');

        if ($record !== null && ! $this->satisfiesScope($user, $record, $scope, $branchId, $grant->conditions_json ?? [])) {
            return $this->finish($user, $companyId, $branchId, $code, $request, 'deny', "Record does not satisfy the granted {$scope} scope.", [], $scope, $ip);
        }
        $recordCompanyId = is_array($record) ? ($record['company_id'] ?? null) : (is_object($record) ? ($record->company_id ?? null) : null);
        if ($record !== null && $recordCompanyId === null) {
            return $this->finish($user, $companyId, $branchId, $code, $request, 'deny', 'Record company context is required for record-level authorization.', [], $scope, $ip);
        }
        if ($record !== null && (int) $recordCompanyId !== $companyId) {
            return $this->finish($user, $companyId, $branchId, $code, $request, 'deny', 'Record belongs to a different company.', [], $scope, $ip);
        }
        if ($record !== null && $membership->branch_id !== null) {
            $recordBranchId = is_array($record) ? ($record['branch_id'] ?? null) : ($record->branch_id ?? null);
            if ((int) $recordBranchId !== (int) $membership->branch_id) {
                return $this->finish($user, $companyId, $branchId, $code, $request, 'deny', 'Record is outside the branch membership boundary.', [], $scope, $ip);
            }
        }

        $recordAttributes = is_array($record) ? $record : (is_object($record) ? (method_exists($record, 'getAttributes') ? $record->getAttributes() : get_object_vars($record)) : []);
        $attributes = array_merge($recordAttributes, $context);
        $obligations = [];
        if ($permission->requires_mfa) $obligations['requires_mfa'] = true;
        if ($permission->requires_reason) $obligations['requires_reason'] = true;
        if ($permission->requires_mfa && empty($context['mfa_verified'])) {
            return $this->finish($user, $companyId, $branchId, $code, $request, 'deny', 'A verified MFA challenge is required for this permission.', $obligations, $scope, $ip);
        }
        if ($permission->requires_reason && ! filled($context['reason'] ?? null)) {
            return $this->finish($user, $companyId, $branchId, $code, $request, 'deny', 'A reason is required for this permission.', $obligations, $scope, $ip);
        }
        if (str_contains($permission->action, 'approve') && (int) ($context['created_by_id'] ?? 0) === (int) $user->id) {
            return $this->finish($user, $companyId, $branchId, $code, $request, 'deny', 'Self-approval is not permitted.', [], $scope, $ip);
        }
        $conditions = $grant->conditions_json ?? [];
        if (! $this->conditionsMatch($conditions, $attributes)) {
            if ($approval || ($conditions['overflow_effect'] ?? null) === 'allow_with_approval') {
                return $this->finish($user, $companyId, $branchId, $code, $request, 'allow_with_approval', 'A configured condition requires an approval workflow before this action can be completed.', $obligations + ['requires_workflow' => true], $scope, $ip);
            }
            return $this->finish($user, $companyId, $branchId, $code, $request, 'deny', 'The permission condition is not satisfied.', [], $scope, $ip);
        }

        // Evaluate stored policies using the safe, deliberately small condition grammar.
        $policies = AuthzPolicy::query()->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('target_permission')->orWhere('target_permission', $code))
            ->where(fn ($q) => $q->whereNull('target_resource')->orWhereIn('target_resource', [$permission->resource, $permission->module.'.'.$permission->resource]))
            ->orderByDesc('priority')->get();
        $hasAllowPolicy = $policies->contains('effect', 'allow');
        $allowPolicyMatched = false;
        foreach ($policies as $policy) {
            $match = $this->expressionMatches((string) $policy->condition_expression, $attributes);
            if ($match && $policy->effect === 'deny') {
                return $this->finish($user, $companyId, $branchId, $code, $request, 'deny', 'Blocked by policy: '.$policy->name, [], $scope, $ip);
            }
            if ($match && $policy->effect === 'allow') $allowPolicyMatched = true;
            if ($match && $policy->effect === 'allow_with_approval') {
                return $this->finish($user, $companyId, $branchId, $code, $request, 'allow_with_approval', 'Policy requires approval: '.$policy->name, $obligations + ['requires_workflow' => true], $scope, $ip);
            }
        }
        if ($hasAllowPolicy && ! $allowPolicyMatched) {
            return $this->finish($user, $companyId, $branchId, $code, $request, 'deny', 'No allow condition matched for this permission.', [], $scope, $ip);
        }

        if ($approval) {
            return $this->finish($user, $companyId, $branchId, $code, $request, 'allow_with_approval', 'Grant requires workflow approval before the protected action.', $obligations + ['requires_workflow' => true], $scope, $ip);
        }
        return $this->finish($user, $companyId, $branchId, $code, $request, 'allow', 'Permission grant, company membership, scope, and policies matched.', $obligations, $scope, $ip);
    }

    public function satisfiesScope(User $user, object|array $record, string $scope, ?int $activeBranchId = null, array $scopeData = []): bool
    {
        $read = fn (string $key) => is_array($record) ? ($record[$key] ?? null) : ($record->{$key} ?? null);
        return match ($scope) {
            'all' => true,
            'branch' => $activeBranchId !== null && (int) $read('branch_id') === $activeBranchId,
            'own' => (int) ($read('owned_by') ?? $read('created_by') ?? $read('user_id') ?? 0) === (int) $user->id,
            'team' => isset($scopeData['team_id']) && (int) $read('team_id') === (int) $scopeData['team_id'],
            'warehouse_ids' => isset($scopeData['warehouse_ids']) && in_array((int) $read('warehouse_id'), array_map('intval', (array) $scopeData['warehouse_ids']), true),
            'custom_filter' => $this->conditionsMatch($scopeData['filter'] ?? [], is_array($record) ? $record : (method_exists($record, 'getAttributes') ? $record->getAttributes() : get_object_vars($record))),
            default => false,
        };
    }

    public function applyScopeToQuery(Builder $query, User $user, string $scope, ?int $activeBranchId = null, array $scopeData = []): Builder
    {
        $table = $query->getModel()->getTable();
        $companyId = isset($scopeData['company_id']) ? (int) $scopeData['company_id'] : null;
        $membershipQuery = AuthzMembership::query()->where('user_id', $user->id)->where('status', 'active');
        if ($companyId) $membershipQuery->where('company_id', $companyId);
        else $membershipQuery->where('is_default_context', true);
        $membership = $membershipQuery->orderByDesc('is_default_context')->orderBy('id')->first();
        if (! $membership || ! Schema::hasColumn($table, 'company_id')) return $query->whereRaw('1 = 0');
        $query->where($table.'.company_id', $membership->company_id);

        $membershipBranch = $membership->branch_id ? (int) $membership->branch_id : null;
        if ($membershipBranch !== null) {
            if (! Schema::hasColumn($table, 'branch_id')) return $query->whereRaw('1 = 0');
            $query->where($table.'.branch_id', $membershipBranch);
        }
        if ($scope === 'branch') {
            $branch = $membershipBranch ?? $activeBranchId;
            return $branch && Schema::hasColumn($table, 'branch_id') ? $query->where($table.'.branch_id', $branch) : $query->whereRaw('1 = 0');
        }
        if ($scope === 'own') {
            $ownerColumn = Schema::hasColumn($table, 'owned_by') ? 'owned_by' : (Schema::hasColumn($table, 'created_by') ? 'created_by' : null);
            return $ownerColumn ? $query->where($table.'.'.$ownerColumn, $user->id) : $query->whereRaw('1 = 0');
        }
        if ($scope === 'team') return isset($scopeData['team_id']) && Schema::hasColumn($table, 'team_id') ? $query->where($table.'.team_id', $scopeData['team_id']) : $query->whereRaw('1 = 0');
        if ($scope === 'warehouse_ids') return ! empty($scopeData['warehouse_ids']) && Schema::hasColumn($table, 'warehouse_id') ? $query->whereIn($table.'.warehouse_id', $scopeData['warehouse_ids']) : $query->whereRaw('1 = 0');
        if ($scope === 'custom_filter') {
            foreach (($scopeData['filter']['equals'] ?? []) as $field => $value) {
                if (! Schema::hasColumn($table, $field)) return $query->whereRaw('1 = 0');
                $query->where($table.'.'.$field, $value);
            }
            return $query;
        }
        return $scope === 'all' ? $query : $query->whereRaw('1 = 0');
    }

    public function filterFields(User $user, string $resource, array $data, ?int $companyId = null, ?int $branchId = null): array
    {
        $policies = AuthzFieldPolicy::query()->where('resource', $resource)->get();
        foreach ($policies as $policy) {
            if (! array_key_exists($policy->field, $data)) continue;
            $allowed = $this->authorize(['user' => $user, 'permission' => $policy->permission_needed, 'company_id' => $companyId, 'branch_id' => $branchId])['allowed'];
            if ($allowed) continue;
            if ($policy->effect === 'mask') {
                $value = (string) $data[$policy->field];
                $data[$policy->field] = mb_strlen($value) > 4 ? str_repeat('*', mb_strlen($value) - 4).mb_substr($value, -4) : '****';
            } else {
                // A response serializer cannot make a field read-only. Fail closed and omit it.
                unset($data[$policy->field]);
            }
        }
        return $data;
    }

    private function conditionsMatch(array $conditions, array $attributes): bool
    {
        foreach ($conditions as $field => $expected) {
            if ($field === 'max_amount') {
                if ((float) ($attributes['amount'] ?? $attributes['total'] ?? 0) > (float) $expected) return false;
            } elseif ($field === 'min_amount') {
                if ((float) ($attributes['amount'] ?? $attributes['total'] ?? 0) < (float) $expected) return false;
            } elseif ($field === 'statuses') {
                if (! in_array($attributes['status'] ?? null, (array) $expected, true)) return false;
            } elseif ($field === 'equals' && is_array($expected)) {
                foreach ($expected as $key => $value) if (($attributes[$key] ?? null) != $value) return false;
            } elseif (! in_array($field, ['overflow_effect'], true)) {
                // Unknown condition keys must not silently turn a restricted grant into an unrestricted one.
                return false;
            }
        }
        return true;
    }

    private function expressionMatches(string $expression, array $attributes): bool
    {
        // Never eval stored expressions. Support only a scalar comparison grammar.
        if (! preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\s*(<=|>=|==|!=|<|>)\s*(?:([0-9]+(?:\.[0-9]+)?)|\'([^\']*)\'|"([^"]*)")$/', trim($expression), $m)) return false;
        $left = $attributes[$m[1]] ?? null;
        $right = $m[3] !== '' ? (float) $m[3] : ($m[4] !== '' ? $m[4] : ($m[5] ?? ''));
        if ($left === null) return false;
        return match ($m[2]) {
            '<=' => is_numeric($left) && (float) $left <= (float) $right,
            '>=' => is_numeric($left) && (float) $left >= (float) $right,
            '<' => is_numeric($left) && (float) $left < (float) $right,
            '>' => is_numeric($left) && (float) $left > (float) $right,
            '==' => (string) $left === (string) $right,
            '!=' => (string) $left !== (string) $right,
        };
    }

    private function finish(?User $user, ?int $companyId, ?int $branchId, string $permission, array $request, string $decision, string $reason, array $obligations, string $scope, ?string $ip): array
    {
        if ($user && empty($request['no_log'])) {
            AuthzAccessLog::query()->create([
                'user_id' => $user->id, 'company_id' => $companyId, 'branch_id' => $branchId,
                'permission_code' => mb_substr($permission, 0, 80),
                'resource_type' => isset($request['resource_type']) ? mb_substr((string) $request['resource_type'], 0, 48) : null,
                'resource_id' => $request['resource_id'] ?? null, 'decision' => $decision,
                'reason' => mb_substr($reason, 0, 255), 'obligations' => $obligations, 'ip' => $ip,
            ]);
        }
        return ['allowed' => $decision === 'allow', 'decision' => $decision, 'reason' => $reason, 'obligations' => $obligations, 'effective_scope' => $scope];
    }
}
