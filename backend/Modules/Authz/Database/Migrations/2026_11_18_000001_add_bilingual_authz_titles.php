<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('authz_roles', function (Blueprint $table): void {
            if (! Schema::hasColumn('authz_roles', 'title_en')) $table->string('title_en', 160)->nullable();
        });
        Schema::table('authz_permissions', function (Blueprint $table): void {
            if (! Schema::hasColumn('authz_permissions', 'title_en')) $table->string('title_en', 160)->nullable();
            if (! Schema::hasColumn('authz_permissions', 'description_en')) $table->text('description_en')->nullable();
        });
        Schema::table('authz_policies', function (Blueprint $table): void {
            if (! Schema::hasColumn('authz_policies', 'name_en')) $table->string('name_en', 160)->nullable();
        });

        // Backfill the canonical CRM company memberships into the authorization context table.
        if (Schema::hasTable('crm_company_users') && Schema::hasTable('authz_memberships')) {
            foreach (DB::table('crm_company_users')->get() as $companyMembership) {
                $branches = collect();
                if (Schema::hasTable('org_user_branches')) {
                    $branches = DB::table('org_user_branches as ub')
                        ->join('org_branches as b', 'b.id', '=', 'ub.branch_id')
                        ->where('ub.user_id', $companyMembership->user_id)
                        ->where('b.company_id', $companyMembership->company_id)
                        ->select('ub.branch_id', 'ub.is_active')->get();
                }
                if ($branches->isEmpty()) $branches->push((object) ['branch_id' => null, 'is_active' => false]);
                foreach ($branches as $branch) {
                    DB::table('authz_memberships')->updateOrInsert(
                        ['user_id' => $companyMembership->user_id, 'company_id' => $companyMembership->company_id, 'branch_id' => $branch->branch_id],
                        ['status' => 'active', 'is_default_context' => (bool) $companyMembership->is_active && (bool) $branch->is_active, 'created_at' => now(), 'updated_at' => now()]
                    );
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('authz_permissions', function (Blueprint $table): void {
            if (Schema::hasColumn('authz_permissions', 'description_en')) $table->dropColumn('description_en');
            if (Schema::hasColumn('authz_permissions', 'title_en')) $table->dropColumn('title_en');
        });
        Schema::table('authz_policies', function (Blueprint $table): void {
            if (Schema::hasColumn('authz_policies', 'name_en')) $table->dropColumn('name_en');
        });
        Schema::table('authz_roles', function (Blueprint $table): void {
            if (Schema::hasColumn('authz_roles', 'title_en')) $table->dropColumn('title_en');
        });
    }
};
