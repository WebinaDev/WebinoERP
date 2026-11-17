<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wf_definitions')) {
            Schema::create('wf_definitions', function (Blueprint $table) {
                $table->id();
                $table->string('name', 160);
                $table->string('module_slug', 32); // sales, docs, accounting, hrm, scm, etc.
                $table->string('entity_type', 64); // quote, sales_invoice, contract, payment, leave, purchase_order
                $table->json('conditions')->nullable(); // min_amount, max_amount, category, roles
                $table->json('steps'); // [{order: 1, type: role|user|direct_manager, target: 'finance_manager', mode: sequential|parallel, sla_hours: 24}]
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('wf_instances')) {
            Schema::create('wf_instances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('definition_id')->constrained('wf_definitions')->cascadeOnDelete();
                $table->string('entity_type', 64);
                $table->unsignedBigInteger('entity_id');
                $table->string('title', 200);
                $table->decimal('amount', 18, 2)->nullable();
                $table->string('status', 32)->default('pending'); // pending, approved, rejected, cancelled
                $table->unsignedInteger('current_step')->default(1);
                $table->json('context')->nullable();
                $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->index(['entity_type', 'entity_id']);
            });
        }

        if (! Schema::hasTable('wf_step_approvals')) {
            Schema::create('wf_step_approvals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('instance_id')->constrained('wf_instances')->cascadeOnDelete();
                $table->unsignedInteger('step_order');
                $table->string('target_role', 64)->nullable();
                $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status', 32)->default('pending'); // pending, approved, rejected
                $table->timestamp('sla_due_at')->nullable();
                $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('decided_at')->nullable();
                $table->text('comment')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('wf_delegations')) {
            Schema::create('wf_delegations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('delegator_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('delegate_id')->constrained('users')->cascadeOnDelete();
                $table->string('module_slug', 32)->nullable(); // null = all
                $table->date('start_date');
                $table->date('end_date');
                $table->text('reason')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('wf_audit_logs')) {
            Schema::create('wf_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('instance_id')->constrained('wf_instances')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action', 32); // initiate, approve, reject, delegate, reminder_sent
                $table->json('payload')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wf_audit_logs');
        Schema::dropIfExists('wf_delegations');
        Schema::dropIfExists('wf_step_approvals');
        Schema::dropIfExists('wf_instances');
        Schema::dropIfExists('wf_definitions');
    }
};
