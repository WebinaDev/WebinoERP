<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hrm_objectives')) {
            Schema::create('hrm_objectives', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('hrm_employees')->cascadeOnDelete();
                $table->foreignId('cycle_id')->nullable()->constrained('hrm_performance_cycles')->nullOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained('hrm_objectives')->nullOnDelete();
                $table->string('title', 200);
                $table->text('description')->nullable();
                $table->string('period', 50)->nullable();
                $table->decimal('weight', 5, 2)->default(1);
                $table->unsignedTinyInteger('progress')->default(0);
                $table->string('status', 20)->default('active');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hrm_key_results')) {
            Schema::create('hrm_key_results', function (Blueprint $table) {
                $table->id();
                $table->foreignId('objective_id')->constrained('hrm_objectives')->cascadeOnDelete();
                $table->string('title', 200);
                $table->decimal('target_value', 14, 2)->default(100);
                $table->decimal('current_value', 14, 2)->default(0);
                $table->string('unit', 30)->nullable();
                $table->unsignedTinyInteger('progress')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hrm_reviews_360')) {
            Schema::create('hrm_reviews_360', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('hrm_employees')->cascadeOnDelete();
                $table->foreignId('cycle_id')->nullable()->constrained('hrm_performance_cycles')->nullOnDelete();
                $table->date('due_date')->nullable();
                $table->string('status', 20)->default('open');
                $table->decimal('average_score', 5, 2)->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hrm_review_360_ratings')) {
            Schema::create('hrm_review_360_ratings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('review_id')->constrained('hrm_reviews_360')->cascadeOnDelete();
                $table->foreignId('rater_employee_id')->nullable()->constrained('hrm_employees')->nullOnDelete();
                $table->unsignedBigInteger('rater_user_id')->nullable();
                $table->string('relationship', 20)->default('peer');
                $table->string('status', 20)->default('pending');
                $table->unsignedTinyInteger('score')->nullable();
                $table->text('feedback')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hrm_review_360_ratings');
        Schema::dropIfExists('hrm_reviews_360');
        Schema::dropIfExists('hrm_key_results');
        Schema::dropIfExists('hrm_objectives');
    }
};
