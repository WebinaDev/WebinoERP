<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prj_task_templates', function (Blueprint $table) {
            if (! Schema::hasColumn('prj_task_templates', 'schedule')) {
                $table->json('schedule')->nullable()->after('payload');
            }
            if (! Schema::hasColumn('prj_task_templates', 'next_run_at')) {
                $table->timestamp('next_run_at')->nullable()->after('schedule');
            }
            if (! Schema::hasColumn('prj_task_templates', 'copy_checklists')) {
                $table->boolean('copy_checklists')->default(true)->after('next_run_at');
            }
            if (! Schema::hasColumn('prj_task_templates', 'copy_assignees')) {
                $table->boolean('copy_assignees')->default(false)->after('copy_checklists');
            }
        });
    }

    public function down(): void
    {
        Schema::table('prj_task_templates', function (Blueprint $table) {
            foreach (['copy_assignees', 'copy_checklists', 'next_run_at', 'schedule'] as $col) {
                if (Schema::hasColumn('prj_task_templates', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
