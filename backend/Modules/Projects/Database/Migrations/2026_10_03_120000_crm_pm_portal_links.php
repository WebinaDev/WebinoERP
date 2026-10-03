<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_account_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('crm_accounts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->unique(['account_id', 'user_id']);
        });

        Schema::table('prj_projects', function (Blueprint $table) {
            $table->foreignId('manager_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
        });

        Schema::table('prj_tickets', function (Blueprint $table) {
            $table->foreignId('customer_account_id')->nullable()->constrained('crm_accounts')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('prj_projects')->nullOnDelete();
            $table->foreignId('converted_task_id')->nullable()->constrained('prj_tasks')->nullOnDelete();
        });

        Schema::table('crm_consultations', function (Blueprint $table) {
            $table->foreignId('converted_project_id')->nullable()->constrained('prj_projects')->nullOnDelete();
        });

        Schema::create('prj_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('prj_projects')->cascadeOnDelete();
            $table->string('title', 255);
            $table->date('due_date')->nullable();
            $table->string('status', 32)->default('open');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prj_milestones');

        Schema::table('crm_consultations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('converted_project_id');
        });

        Schema::table('prj_tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('converted_task_id');
            $table->dropConstrainedForeignId('project_id');
            $table->dropConstrainedForeignId('customer_account_id');
        });

        Schema::table('prj_projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manager_user_id');
            $table->dropColumn(['start_date', 'due_date']);
        });

        Schema::dropIfExists('crm_account_users');
    }
};
