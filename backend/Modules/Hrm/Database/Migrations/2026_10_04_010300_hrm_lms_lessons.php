<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hrm_training_lessons')) {
            Schema::create('hrm_training_lessons', function (Blueprint $table) {
                $table->id();
                $table->foreignId('course_id')->constrained('hrm_training_courses')->cascadeOnDelete();
                $table->string('title', 200);
                $table->text('body')->nullable();
                $table->string('content_type', 20)->default('text');
                $table->string('material_url', 500)->nullable();
                $table->unsignedSmallInteger('duration_minutes')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_required')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hrm_lesson_progress')) {
            Schema::create('hrm_lesson_progress', function (Blueprint $table) {
                $table->id();
                $table->foreignId('lesson_id')->constrained('hrm_training_lessons')->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('hrm_employees')->cascadeOnDelete();
                $table->foreignId('enrollment_id')->nullable()->constrained('hrm_training_enrollments')->nullOnDelete();
                $table->unsignedTinyInteger('progress_percent')->default(0);
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->unique(['lesson_id', 'employee_id']);
            });
        }

        if (! Schema::hasTable('hrm_training_certificates')) {
            Schema::create('hrm_training_certificates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('course_id')->constrained('hrm_training_courses')->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('hrm_employees')->cascadeOnDelete();
                $table->foreignId('enrollment_id')->nullable()->constrained('hrm_training_enrollments')->nullOnDelete();
                $table->string('serial_no', 40);
                $table->timestamp('issued_at')->nullable();
                $table->longText('html')->nullable();
                $table->string('pdf_path', 255)->nullable();
                $table->string('signer_name', 150)->nullable();
                $table->timestamps();
                $table->unique(['course_id', 'employee_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hrm_training_certificates');
        Schema::dropIfExists('hrm_lesson_progress');
        Schema::dropIfExists('hrm_training_lessons');
    }
};
