<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hrm_timesheets')) {
            Schema::create('hrm_timesheets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('hrm_employees')->cascadeOnDelete();
                $table->unsignedBigInteger('project_id')->nullable();
                $table->unsignedBigInteger('task_id')->nullable();
                $table->string('project_name', 200)->nullable();
                $table->string('task_name', 200)->nullable();
                $table->date('work_date');
                $table->decimal('hours', 6, 2);
                $table->text('description')->nullable();
                $table->string('status', 20)->default('draft');
                $table->timestamp('submitted_at')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->text('decision_note')->nullable();
                $table->timestamps();
                $table->index(['project_id', 'work_date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hrm_timesheets');
    }
};
