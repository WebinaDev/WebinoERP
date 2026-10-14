<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_academy_chapters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('marketing_academy_courses')->cascadeOnDelete();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::table('marketing_academy_lessons', function (Blueprint $table) {
            $table->foreignId('chapter_id')->nullable()->after('course_id')->constrained('marketing_academy_chapters')->nullOnDelete();
            $table->text('summary')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('marketing_academy_lessons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('chapter_id');
            $table->dropColumn('summary');
        });
        Schema::dropIfExists('marketing_academy_chapters');
    }
};
