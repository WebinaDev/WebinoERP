<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hrm_onboarding_templates')) {
            Schema::create('hrm_onboarding_templates', function (Blueprint $table) {
                $table->id();
                $table->string('name', 150);
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hrm_onboarding_template_items')) {
            Schema::create('hrm_onboarding_template_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('template_id')->constrained('hrm_onboarding_templates')->cascadeOnDelete();
                $table->string('title', 200);
                $table->text('description')->nullable();
                $table->string('document_category', 40)->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('required')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hrm_onboardings')) {
            Schema::create('hrm_onboardings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('hrm_employees')->cascadeOnDelete();
                $table->foreignId('template_id')->nullable()->constrained('hrm_onboarding_templates')->nullOnDelete();
                $table->unsignedBigInteger('applicant_id')->nullable();
                $table->string('status', 20)->default('in_progress');
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hrm_onboarding_tasks')) {
            Schema::create('hrm_onboarding_tasks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('onboarding_id')->constrained('hrm_onboardings')->cascadeOnDelete();
                $table->string('title', 200);
                $table->text('description')->nullable();
                $table->string('document_category', 40)->nullable();
                $table->unsignedBigInteger('document_id')->nullable();
                $table->string('status', 20)->default('pending');
                $table->date('due_date')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->string('owner', 20)->default('employee');
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hrm_onboarding_tasks');
        Schema::dropIfExists('hrm_onboardings');
        Schema::dropIfExists('hrm_onboarding_template_items');
        Schema::dropIfExists('hrm_onboarding_templates');
    }
};
