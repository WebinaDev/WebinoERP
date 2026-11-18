<?php

namespace Modules\Org\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Auth\Access\AuthorizationException;
use Modules\Crm\Entities\CrmCompany;
use Modules\Org\Entities\OrgBranch;

class OrgContextService
{
    public function getActiveCompany(?User $user): ?CrmCompany
    {
        if (! $user) {
            return CrmCompany::query()->where('is_default', true)->first() ?? CrmCompany::query()->first();
        }

        if (Schema::hasTable('crm_company_users')) {
            $row = DB::table('crm_company_users')
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->first();
            if ($row) {
                $c = CrmCompany::query()->find($row->company_id);
                if ($c) return $c;
            }
        }

        return CrmCompany::query()->where('is_default', true)->first() ?? CrmCompany::query()->first();
    }

    public function getActiveBranch(?User $user, ?int $companyId = null): ?OrgBranch
    {
        if (! $user || ! Schema::hasTable('org_user_branches')) {
            return $companyId
                ? OrgBranch::query()->where('company_id', $companyId)->where('is_active', true)->first()
                : OrgBranch::query()->where('is_active', true)->first();
        }

        $query = DB::table('org_user_branches as ub')
            ->join('org_branches as b', 'ub.branch_id', '=', 'b.id')
            ->where('ub.user_id', $user->id)
            ->where('ub.is_active', true);

        if ($companyId) {
            $query->where('b.company_id', $companyId);
        }

        $row = $query->select('b.id')->first();
        if ($row) {
            return OrgBranch::query()->find($row->id);
        }

        return $companyId
            ? OrgBranch::query()->where('company_id', $companyId)->where('is_active', true)->first()
            : OrgBranch::query()->where('is_active', true)->first();
    }

    public function switchContext(User $user, int $companyId, ?int $branchId = null): array
    {
        return DB::transaction(function () use ($user, $companyId, $branchId): array {
            $company = CrmCompany::query()->findOrFail($companyId);
            if (Schema::hasTable('crm_company_users')) {
                $isMember = DB::table('crm_company_users')->where('user_id', $user->id)->where('company_id', $company->id)->exists();
                if (! $isMember) throw new AuthorizationException('You are not a member of the selected company.');
            }

            if (! $branchId && Schema::hasTable('org_user_branches')) {
                $assignedBranch = DB::table('org_user_branches as ub')
                    ->join('org_branches as b', 'b.id', '=', 'ub.branch_id')
                    ->where('ub.user_id', $user->id)->where('b.company_id', $company->id)
                    ->where('b.is_active', true)->orderByDesc('ub.is_active')->orderBy('ub.id')->value('ub.branch_id');
                if ($assignedBranch) $branchId = (int) $assignedBranch;
            }

            $branch = null;
            if ($branchId) {
                $branch = OrgBranch::query()->where('company_id', $company->id)->where('is_active', true)->findOrFail($branchId);
                if (Schema::hasTable('org_user_branches')) {
                    $hasBranchMembership = DB::table('org_user_branches as ub')
                        ->join('org_branches as b', 'b.id', '=', 'ub.branch_id')
                        ->where('ub.user_id', $user->id)->where('b.company_id', $company->id)->exists();
                    if ($hasBranchMembership && ! DB::table('org_user_branches')->where('user_id', $user->id)->where('branch_id', $branch->id)->exists()) {
                        throw new AuthorizationException('You are not a member of the selected branch.');
                    }
                }
            }

            if (Schema::hasTable('crm_company_users')) {
                DB::table('crm_company_users')->where('user_id', $user->id)->update(['is_active' => false, 'updated_at' => now()]);
                DB::table('crm_company_users')->where('user_id', $user->id)->where('company_id', $company->id)->update(['is_active' => true, 'updated_at' => now()]);
            }
            if ($branch && Schema::hasTable('org_user_branches')) {
                DB::table('org_user_branches')->where('user_id', $user->id)->update(['is_active' => false, 'updated_at' => now()]);
                DB::table('org_user_branches')->where('user_id', $user->id)->where('branch_id', $branch->id)->update(['is_active' => true, 'updated_at' => now()]);
            }
            if (Schema::hasTable('authz_memberships')) {
                DB::table('authz_memberships')->where('user_id', $user->id)->update(['is_default_context' => false, 'updated_at' => now()]);
                DB::table('authz_memberships')->updateOrInsert(
                    ['user_id' => $user->id, 'company_id' => $company->id, 'branch_id' => $branch?->id],
                    ['status' => 'active', 'is_default_context' => true, 'created_at' => now(), 'updated_at' => now()]
                );
            }

            return ['company' => $company, 'branch' => $branch];
        });
    }
}
