<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prj_projects', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('status', 50)->default('active');
            $table->foreignId('customer_account_id')->nullable()->constrained('crm_accounts')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('prj_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->foreignId('project_id')->nullable()->constrained('prj_projects')->nullOnDelete();
            $table->string('status', 50)->default('draft');
            $table->decimal('amount', 15, 2)->default(0);
            $table->foreignId('customer_account_id')->nullable()->constrained('crm_accounts')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('prj_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained('prj_projects')->cascadeOnDelete();
            $table->string('title', 255);
            $table->string('status', 50)->default('open');
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('due_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('prj_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('subject', 255);
            $table->text('body')->nullable();
            $table->string('status', 50)->default('open');
            $table->foreignId('customer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('prj_pro_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number', 50)->nullable();
            $table->foreignId('contract_id')->nullable()->constrained('prj_contracts')->nullOnDelete();
            $table->string('status', 50)->default('draft');
            $table->decimal('total', 15, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('prj_appointments', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('customer_account_id')->nullable()->constrained('crm_accounts')->nullOnDelete();
            $table->string('status', 50)->default('scheduled');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prj_appointments');
        Schema::dropIfExists('prj_pro_invoices');
        Schema::dropIfExists('prj_tickets');
        Schema::dropIfExists('prj_tasks');
        Schema::dropIfExists('prj_contracts');
        Schema::dropIfExists('prj_projects');
    }
};
