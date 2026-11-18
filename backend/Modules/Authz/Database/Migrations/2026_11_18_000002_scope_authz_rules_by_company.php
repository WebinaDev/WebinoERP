<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('authz_policies', function (Blueprint $table): void {
            if (! Schema::hasColumn('authz_policies', 'company_id')) {
                $table->foreignId('company_id')->nullable()->constrained('crm_companies')->cascadeOnDelete();
            }
        });

        Schema::table('authz_field_policies', function (Blueprint $table): void {
            if (! Schema::hasColumn('authz_field_policies', 'company_id')) {
                $table->foreignId('company_id')->nullable()->constrained('crm_companies')->cascadeOnDelete();
                $table->dropUnique('authz_field_policies_resource_field_unique');
                $table->unique(['company_id', 'resource', 'field'], 'authz_field_policy_company_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('authz_field_policies', function (Blueprint $table): void {
            $table->dropUnique('authz_field_policy_company_unique');
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
            $table->unique(['resource', 'field']);
        });
        Schema::table('authz_policies', function (Blueprint $table): void {
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
        });
    }
};
