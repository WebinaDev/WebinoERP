<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prj_tasks', function (Blueprint $table) {
            $table->timestamp('start_at')->nullable();
            $table->unsignedSmallInteger('duration_days')->nullable();
        });

        Schema::table('prj_kanban_cards', function (Blueprint $table) {
            $table->string('swimlane_key', 64)->nullable();
        });

        Schema::create('prj_gantt_baselines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained('prj_projects')->nullOnDelete();
            $table->string('name');
            $table->json('snapshot');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('prj_resource_capacities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->decimal('hours', 5, 2)->default(8);
            $table->timestamps();
            $table->unique(['user_id', 'weekday']);
        });

        Schema::create('prj_leave_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('kind', 32)->default('leave');
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('prj_offline_ops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('client_id');
            $table->string('action', 40);
            $table->json('payload')->nullable();
            $table->json('result')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prj_offline_ops');
        Schema::dropIfExists('prj_leave_entries');
        Schema::dropIfExists('prj_resource_capacities');
        Schema::dropIfExists('prj_gantt_baselines');
        Schema::table('prj_kanban_cards', function (Blueprint $table) {
            $table->dropColumn('swimlane_key');
        });
        Schema::table('prj_tasks', function (Blueprint $table) {
            $table->dropColumn(['start_at', 'duration_days']);
        });
    }
};
