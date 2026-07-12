<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prj_time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained('prj_tasks')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('prj_projects')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->boolean('is_running')->default(false)->index();
            $table->boolean('is_billable')->default(false);
            $table->text('description')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prj_time_entries');
    }
};
