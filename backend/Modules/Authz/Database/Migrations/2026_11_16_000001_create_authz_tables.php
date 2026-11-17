<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Memberships (user in company/branch context)
        if (! Schema::hasTable('authz_memberships')) {
            Schema::create('authz_memberships', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('company_id')->constrained('crm_companies')->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('org_branches')->nullOnDelete();
                $table->string('status', 24)->default('active'); // active, suspended, invited
                $table->boolean('is_default_context')->default(false);
                $table->timestamps();
                $table->unique(['user_id', 'company_id', 'branch_id']);
            });
        }

        // 2. Atomic permissions catalog (module.resource.action)
        if (! Schema::hasTable('authz_permissions')) {
            Schema::create('authz_permissions', function (Blueprint $table) {
                $table->id();
                $table->string('code', 80)->unique(); // e.g. sales.invoice.approve
                $table->string('module', 32); // sales, scm, accounting, hrm, etc.
                $table->string('resource', 48); // invoice, quote, order, stock, etc.
                $table->string('action', 32); // view, create, update, delete, approve, export, etc.
                $table->string('title_fa', 160);
                $table->text('description')->nullable();
                $table->string('risk_level', 20)->default('low'); // low, medium, high, critical
                $table->boolean('requires_reason')->default(false);
                $table->boolean('requires_mfa')->default(false);
                $table->timestamps();
            });
        }

        // 3. Roles
        if (! Schema::hasTable('authz_roles')) {
            Schema::create('authz_roles', function (Blueprint $table) {
                $table->id();
                $table->string('code', 64);
                $table->string('title_fa', 160);
                $table->foreignId('company_id')->nullable()->constrained('crm_companies')->nullOnDelete();
                $table->boolean('is_system')->default(false);
                $table->timestamps();
                $table->unique(['code', 'company_id']);
            });
        }

        // 4. Role permissions with scope & effect
        if (! Schema::hasTable('authz_role_permissions')) {
            Schema::create('authz_role_permissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('role_id')->constrained('authz_roles')->cascadeOnDelete();
                $table->foreignId('permission_id')->constrained('authz_permissions')->cascadeOnDelete();
                $table->string('scope', 32)->default('all'); // all, branch, own, team, custom_filter
                $table->json('conditions_json')->nullable(); // amount <= X, status == 'draft'
                $table->string('effect', 16)->default('allow'); // allow, deny, allow_with_approval
                $table->timestamps();
                $table->unique(['role_id', 'permission_id']);
            });
        }

        // 5. User roles
        if (! Schema::hasTable('authz_user_roles')) {
            Schema::create('authz_user_roles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('company_id')->constrained('crm_companies')->cascadeOnDelete();
                $table->foreignId('role_id')->constrained('authz_roles')->cascadeOnDelete();
                $table->json('scope_json')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'company_id', 'role_id']);
            });
        }

        // 6. Direct user permission exceptions
        if (! Schema::hasTable('authz_user_permissions')) {
            Schema::create('authz_user_permissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('company_id')->constrained('crm_companies')->cascadeOnDelete();
                $table->foreignId('permission_id')->constrained('authz_permissions')->cascadeOnDelete();
                $table->string('scope', 32)->default('all');
                $table->json('conditions_json')->nullable();
                $table->string('effect', 16)->default('allow'); // allow, deny
                $table->timestamps();
                $table->unique(['user_id', 'company_id', 'permission_id']);
            });
        }

        // 7. ABAC Policies
        if (! Schema::hasTable('authz_policies')) {
            Schema::create('authz_policies', function (Blueprint $table) {
                $table->id();
                $table->string('name', 160);
                $table->string('target_permission', 80)->nullable(); // e.g. sales.invoice.approve or null
                $table->string('target_resource', 48)->nullable();
                $table->string('condition_expression', 255); // e.g. amount <= 50000000
                $table->string('effect', 16)->default('allow'); // allow, deny
                $table->integer('priority')->default(100);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 8. Field-level Security Policies
        if (! Schema::hasTable('authz_field_policies')) {
            Schema::create('authz_field_policies', function (Blueprint $table) {
                $table->id();
                $table->string('resource', 48); // e.g. sales.invoice
                $table->string('field', 64); // e.g. cost_price, buy_price, sheba
                $table->string('permission_needed', 80); // e.g. sales.cost.view
                $table->string('effect', 20)->default('hide'); // hide, mask, read_only
                $table->timestamps();
                $table->unique(['resource', 'field']);
            });
        }

        // 9. Delegations
        if (! Schema::hasTable('authz_delegations')) {
            Schema::create('authz_delegations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('delegator_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('delegate_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('company_id')->constrained('crm_companies')->cascadeOnDelete();
                $table->string('scope_module', 32)->nullable(); // null = all modules
                $table->date('start_date');
                $table->date('end_date');
                $table->text('reason')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 10. Audit access logs
        if (! Schema::hasTable('authz_access_logs')) {
            Schema::create('authz_access_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('company_id')->nullable()->constrained('crm_companies')->nullOnDelete();
                $table->foreignId('branch_id')->nullable();
                $table->string('permission_code', 80);
                $table->string('resource_type', 48)->nullable();
                $table->unsignedBigInteger('resource_id')->nullable();
                $table->string('decision', 16); // allow, deny, allow_with_approval
                $table->string('reason', 255)->nullable();
                $table->json('obligations')->nullable();
                $table->string('ip', 45)->nullable();
                $table->timestamps();
                $table->index(['user_id', 'created_at']);
                $table->index(['permission_code', 'decision']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('authz_access_logs');
        Schema::dropIfExists('authz_delegations');
        Schema::dropIfExists('authz_field_policies');
        Schema::dropIfExists('authz_policies');
        Schema::dropIfExists('authz_user_permissions');
        Schema::dropIfExists('authz_user_roles');
        Schema::dropIfExists('authz_role_permissions');
        Schema::dropIfExists('authz_roles');
        Schema::dropIfExists('authz_permissions');
        Schema::dropIfExists('authz_memberships');
    }
};
