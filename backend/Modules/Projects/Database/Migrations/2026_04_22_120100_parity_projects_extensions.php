<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prj_workflow_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('color', 7)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        DB::table('prj_workflow_statuses')->insert([
            ['name' => 'todo', 'color' => '#64748b', 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'in_progress', 'color' => '#3b82f6', 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'review', 'color' => '#f59e0b', 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'done', 'color' => '#22c55e', 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
        ]);

        Schema::table('prj_tasks', function (Blueprint $table) {
            $table->string('priority', 20)->nullable()->after('status');
            $table->unsignedBigInteger('workflow_status_id')->nullable()->after('priority');
            $table->string('label', 100)->nullable();
            $table->text('content')->nullable();
            $table->unsignedBigInteger('sprint_id')->nullable();
            $table->json('checklist')->nullable();
            $table->json('time_logs')->nullable();
            $table->foreign('workflow_status_id')->references('id')->on('prj_workflow_statuses')->nullOnDelete();
        });

        Schema::create('prj_task_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('prj_tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('prj_task_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_task_id')->constrained('prj_tasks')->cascadeOnDelete();
            $table->foreignId('target_task_id')->constrained('prj_tasks')->cascadeOnDelete();
            $table->string('link_type', 50)->default('relates');
            $table->timestamps();
        });

        Schema::table('prj_tickets', function (Blueprint $table) {
            $table->string('priority', 20)->nullable()->after('status');
            $table->string('department', 100)->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
        });

        Schema::create('prj_ticket_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('prj_tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->json('attachments')->nullable();
            $table->timestamps();
        });

        Schema::create('prj_contract_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('prj_contracts')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('due_date');
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_ref', 191)->nullable();
            $table->timestamps();
        });

        Schema::table('prj_contracts', function (Blueprint $table) {
            $table->json('installments_data')->nullable()->after('amount');
            $table->timestamp('signed_at')->nullable();
            $table->foreignId('lead_id')->nullable()->constrained('crm_leads')->nullOnDelete();
        });

        Schema::table('prj_pro_invoices', function (Blueprint $table) {
            $table->json('items')->nullable()->after('total');
            $table->decimal('discount', 15, 2)->default(0)->after('items');
            $table->text('notes')->nullable();
            $table->foreignId('customer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('prj_projects')->nullOnDelete();
        });

        Schema::table('prj_appointments', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('title');
            $table->foreignId('customer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('prj_products', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('sku', 100)->nullable();
            $table->decimal('price', 15, 2)->default(0);
            $table->unsignedBigInteger('task_template_id')->nullable();
            $table->string('service_task_type', 50)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prj_products');
        Schema::table('prj_appointments', function (Blueprint $table) {
            $table->dropForeign(['customer_user_id']);
            $table->dropForeign(['created_by']);
            $table->dropColumn(['notes', 'customer_user_id', 'created_by']);
        });
        Schema::table('prj_pro_invoices', function (Blueprint $table) {
            $table->dropForeign(['customer_user_id']);
            $table->dropForeign(['project_id']);
            $table->dropColumn(['items', 'discount', 'notes', 'customer_user_id', 'project_id']);
        });
        Schema::table('prj_contracts', function (Blueprint $table) {
            $table->dropForeign(['lead_id']);
            $table->dropColumn(['installments_data', 'signed_at', 'lead_id']);
        });
        Schema::dropIfExists('prj_contract_installments');
        Schema::dropIfExists('prj_ticket_replies');
        Schema::table('prj_tickets', function (Blueprint $table) {
            $table->dropColumn(['priority', 'department', 'rating']);
        });
        Schema::dropIfExists('prj_task_links');
        Schema::dropIfExists('prj_task_comments');
        Schema::table('prj_tasks', function (Blueprint $table) {
            $table->dropForeign(['workflow_status_id']);
            $table->dropColumn([
                'priority', 'workflow_status_id', 'label', 'content', 'sprint_id', 'checklist', 'time_logs',
            ]);
        });
        Schema::dropIfExists('prj_workflow_statuses');
    }
};
