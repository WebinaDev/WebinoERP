<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hrm_shift_rotations')) {
            Schema::create('hrm_shift_rotations', function (Blueprint $table) {
                $table->id();
                $table->string('name', 150);
                $table->unsignedSmallInteger('cycle_length')->default(7);
                $table->json('pattern');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hrm_shift_assignments')) {
            Schema::create('hrm_shift_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('hrm_employees')->cascadeOnDelete();
                $table->foreignId('shift_template_id')->nullable()->constrained('hrm_shift_templates')->nullOnDelete();
                $table->date('work_date');
                $table->foreignId('rotation_id')->nullable()->constrained('hrm_shift_rotations')->nullOnDelete();
                $table->string('source', 20)->default('manual');
                $table->boolean('is_off')->default(false);
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->unique(['employee_id', 'work_date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hrm_shift_assignments');
        Schema::dropIfExists('hrm_shift_rotations');
    }
};
