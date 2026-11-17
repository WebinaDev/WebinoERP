<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ast_assets')) {
            Schema::create('ast_assets', function (Blueprint $table) {
                $table->id();
                $table->string('asset_code', 48)->unique();
                $table->string('name', 160);
                $table->string('category', 64); // machinery, vehicles, it_equipment, furniture, building
                $table->foreignId('company_id')->nullable()->constrained('crm_companies')->nullOnDelete();
                $table->foreignId('branch_id')->nullable();
                $table->string('location', 160)->nullable();
                $table->foreignId('custodian_id')->nullable()->constrained('hrm_employees')->nullOnDelete();
                $table->date('purchase_date')->nullable();
                $table->decimal('purchase_cost', 18, 2)->default(0);
                $table->decimal('salvage_value', 18, 2)->default(0);
                $table->unsignedInteger('useful_life_months')->default(60);
                $table->string('depreciation_method', 32)->default('straight_line'); // straight_line, declining_balance
                $table->decimal('accumulated_depreciation', 18, 2)->default(0);
                $table->decimal('book_value', 18, 2)->default(0);
                $table->string('status', 24)->default('active'); // active, maintenance, disposed, retired
                $table->foreignId('asset_account_id')->nullable(); // GL Asset Account
                $table->foreignId('depreciation_account_id')->nullable(); // GL Depreciation Expense Account
                $table->foreignId('accum_depr_account_id')->nullable(); // GL Accumulated Depr Account
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ast_depreciation_runs')) {
            Schema::create('ast_depreciation_runs', function (Blueprint $table) {
                $table->id();
                $table->string('run_number', 48)->unique();
                $table->foreignId('company_id')->nullable()->constrained('crm_companies')->nullOnDelete();
                $table->date('period_date');
                $table->decimal('total_depreciation', 18, 2)->default(0);
                $table->unsignedInteger('assets_count')->default(0);
                $table->foreignId('journal_entry_id')->nullable();
                $table->string('status', 20)->default('posted');
                $table->json('details')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ast_maintenance_plans')) {
            Schema::create('ast_maintenance_plans', function (Blueprint $table) {
                $table->id();
                $table->foreignId('asset_id')->constrained('ast_assets')->cascadeOnDelete();
                $table->string('title', 160);
                $table->string('frequency', 24)->default('monthly'); // daily, weekly, monthly, quarterly, annual
                $table->unsignedInteger('interval_days')->default(30);
                $table->date('last_performed_at')->nullable();
                $table->date('next_due_date')->nullable();
                $table->text('checklist')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ast_maintenance_orders')) {
            Schema::create('ast_maintenance_orders', function (Blueprint $table) {
                $table->id();
                $table->string('order_number', 48)->unique();
                $table->foreignId('asset_id')->constrained('ast_assets')->cascadeOnDelete();
                $table->foreignId('plan_id')->nullable()->constrained('ast_maintenance_plans')->nullOnDelete();
                $table->string('type', 20)->default('preventive'); // preventive, corrective, overhaul
                $table->string('priority', 20)->default('medium');
                $table->string('status', 24)->default('scheduled'); // scheduled, in_progress, completed, cancelled
                $table->date('scheduled_date');
                $table->dateTime('completed_at')->nullable();
                $table->text('description')->nullable();
                $table->text('work_done')->nullable();
                $table->decimal('labor_cost', 18, 2)->default(0);
                $table->decimal('parts_cost', 18, 2)->default(0);
                $table->decimal('total_cost', 18, 2)->default(0);
                $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ast_insurances')) {
            Schema::create('ast_insurances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('asset_id')->constrained('ast_assets')->cascadeOnDelete();
                $table->string('policy_number', 64);
                $table->string('insurer_name', 160);
                $table->date('start_date');
                $table->date('end_date');
                $table->decimal('premium_amount', 18, 2)->default(0);
                $table->decimal('coverage_amount', 18, 2)->default(0);
                $table->string('status', 20)->default('active');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ast_insurances');
        Schema::dropIfExists('ast_maintenance_orders');
        Schema::dropIfExists('ast_maintenance_plans');
        Schema::dropIfExists('ast_depreciation_runs');
        Schema::dropIfExists('ast_assets');
    }
};
