<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prj_epics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('prj_projects')->cascadeOnDelete();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('state', 50)->default('open')->index();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();
        });

        Schema::create('prj_sprint_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sprint_id')->constrained('prj_sprints')->cascadeOnDelete();
            $table->foreignId('project_task_id')->constrained('prj_tasks')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['sprint_id', 'project_task_id']);
        });

        if (! Schema::hasColumn('prj_tasks', 'epic_id')) {
            Schema::table('prj_tasks', function (Blueprint $table) {
                $table->foreignId('epic_id')->nullable()->after('sprint_id')->constrained('prj_epics')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('prj_tasks', 'epic_id')) {
            Schema::table('prj_tasks', function (Blueprint $table) {
                $table->dropForeign(['epic_id']);
                $table->dropColumn('epic_id');
            });
        }

        Schema::dropIfExists('prj_sprint_tasks');
        Schema::dropIfExists('prj_epics');
    }
};
