<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prj_projects', function (Blueprint $table) {
            if (! Schema::hasColumn('prj_projects', 'budget_amount')) {
                $table->decimal('budget_amount', 15, 2)->nullable();
            }
            if (! Schema::hasColumn('prj_projects', 'budget_hours')) {
                $table->decimal('budget_hours', 10, 2)->nullable();
            }
            if (! Schema::hasColumn('prj_projects', 'hourly_rate')) {
                $table->decimal('hourly_rate', 12, 2)->nullable();
            }
        });

        Schema::table('prj_tasks', function (Blueprint $table) {
            if (! Schema::hasColumn('prj_tasks', 'estimate_hours')) {
                $table->decimal('estimate_hours', 8, 2)->nullable();
            }
            if (! Schema::hasColumn('prj_tasks', 'starts_at')) {
                $table->timestamp('starts_at')->nullable();
            }
        });

        Schema::table('prj_tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('prj_tickets', 'sla_first_due_at')) {
                $table->timestamp('sla_first_due_at')->nullable();
            }
            if (! Schema::hasColumn('prj_tickets', 'sla_resolve_due_at')) {
                $table->timestamp('sla_resolve_due_at')->nullable();
            }
            if (! Schema::hasColumn('prj_tickets', 'first_responded_at')) {
                $table->timestamp('first_responded_at')->nullable();
            }
            if (! Schema::hasColumn('prj_tickets', 'sla_breached_at')) {
                $table->timestamp('sla_breached_at')->nullable();
            }
        });

        Schema::create('prj_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('prj_projects')->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained('prj_tasks')->nullOnDelete();
            $table->unsignedBigInteger('family_id')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->string('name', 255);
            $table->string('disk', 32)->default('public');
            $table->string('path', 255);
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->boolean('shared_with_client')->default(false);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['project_id', 'family_id']);
        });

        Schema::create('prj_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('prj_projects')->cascadeOnDelete();
            $table->foreignId('customer_account_id')->nullable()->constrained('crm_accounts')->nullOnDelete();
            $table->string('title', 255);
            $table->text('note')->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('decision_note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });

        Schema::create('prj_connectors', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 40);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('disconnected');
            $table->string('label', 120)->nullable();
            $table->json('config')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->string('last_message', 255)->nullable();
            $table->timestamps();
            $table->index(['kind', 'user_id']);
        });

        Schema::create('ops_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('module', 32);
            $table->string('action', 64);
            $table->string('subject_type', 80);
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('changes')->nullable();
            $table->timestamps();
            $table->index(['module', 'created_at']);
        });

        Schema::create('dashboard_widget_prefs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('widget_key', 64);
            $table->boolean('is_visible')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'widget_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_widget_prefs');
        Schema::dropIfExists('ops_audit_logs');
        Schema::dropIfExists('prj_connectors');
        Schema::dropIfExists('prj_approvals');
        Schema::dropIfExists('prj_files');

        Schema::table('prj_tickets', function (Blueprint $table) {
            foreach (['sla_breached_at', 'first_responded_at', 'sla_resolve_due_at', 'sla_first_due_at'] as $column) {
                if (Schema::hasColumn('prj_tickets', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('prj_tasks', function (Blueprint $table) {
            foreach (['starts_at', 'estimate_hours'] as $column) {
                if (Schema::hasColumn('prj_tasks', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('prj_projects', function (Blueprint $table) {
            foreach (['hourly_rate', 'budget_hours', 'budget_amount'] as $column) {
                if (Schema::hasColumn('prj_projects', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
