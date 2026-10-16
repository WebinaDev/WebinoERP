<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** LMS details: course profile (instructor, students, materials, final exam...) and per-lesson duration/kind/quiz. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_academy_courses', function (Blueprint $table) {
            $table->json('lms')->nullable();
        });
        Schema::table('marketing_academy_lessons', function (Blueprint $table) {
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->string('kind', 20)->default('video');
            $table->boolean('is_preview')->default(false);
            $table->json('quiz')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('marketing_academy_lessons', function (Blueprint $table) {
            $table->dropColumn(['duration_minutes', 'kind', 'is_preview', 'quiz']);
        });
        Schema::table('marketing_academy_courses', function (Blueprint $table) {
            $table->dropColumn('lms');
        });
    }
};
