<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prj_contracts', function (Blueprint $table) {
            if (! Schema::hasColumn('prj_contracts', 'customer_user_id')) {
                $table->foreignId('customer_user_id')->nullable()->after('lead_id')->constrained('users')->nullOnDelete();
            }
        });

        Schema::table('prj_tasks', function (Blueprint $table) {
            if (! Schema::hasColumn('prj_tasks', 'category_id')) {
                $table->foreignId('category_id')->nullable()->after('workflow_status_id')->constrained('core_task_categories')->nullOnDelete();
            }
            if (! Schema::hasColumn('prj_tasks', 'recurrence')) {
                $table->json('recurrence')->nullable()->after('time_logs');
            }
        });

        Schema::create('prj_sprints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('prj_projects')->cascadeOnDelete();
            $table->string('name', 191);
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->string('status', 50)->default('planned');
            $table->timestamps();
        });

        Schema::create('prj_task_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained('prj_projects')->nullOnDelete();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->json('payload')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('prj_projects', function (Blueprint $table) {
            if (! Schema::hasColumn('prj_projects', 'is_template')) {
                $table->boolean('is_template')->default(false)->after('status');
            }
        });

        Schema::table('prj_products', function (Blueprint $table) {
            if (! Schema::hasColumn('prj_products', 'task_template')) {
                $table->json('task_template')->nullable()->after('service_task_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('prj_products', function (Blueprint $table) {
            if (Schema::hasColumn('prj_products', 'task_template')) {
                $table->dropColumn('task_template');
            }
        });

        Schema::table('prj_projects', function (Blueprint $table) {
            if (Schema::hasColumn('prj_projects', 'is_template')) {
                $table->dropColumn('is_template');
            }
        });

        Schema::dropIfExists('prj_task_templates');

        Schema::table('prj_tasks', function (Blueprint $table) {
            if (Schema::hasColumn('prj_tasks', 'category_id')) {
                $table->dropForeign(['category_id']);
                $table->dropColumn('category_id');
            }
            if (Schema::hasColumn('prj_tasks', 'recurrence')) {
                $table->dropColumn('recurrence');
            }
        });

        Schema::dropIfExists('prj_sprints');

        Schema::table('prj_contracts', function (Blueprint $table) {
            if (Schema::hasColumn('prj_contracts', 'customer_user_id')) {
                $table->dropForeign(['customer_user_id']);
                $table->dropColumn('customer_user_id');
            }
        });
    }
};
