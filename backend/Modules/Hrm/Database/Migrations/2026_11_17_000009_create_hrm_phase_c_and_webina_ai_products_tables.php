<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. سبد و اشتراک محصولات و توکن‌های وبینا (Webina AI & Cloud Gateway)
        if (! Schema::hasTable('webina_ai_subscriptions')) {
            Schema::create('webina_ai_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_domain', 150)->index(); // دامنه وب‌سایت یا شناسه سازمان
                $table->string('tenant_name', 150);
                $table->string('plan_sku', 64)->default('webina_standard'); // webina_basic, webina_standard, webina_enterprise
                $table->string('plan_title', 120);
                $table->string('api_token_hash', 64)->unique(); // توکن احراز هویت سرویس هوش مصنوعی
                $table->string('gateway_endpoint', 200)->default('https://webina.dev/api/v1/ai');
                $table->decimal('price_toman', 15, 2)->default(0);
                $table->unsignedInteger('monthly_quota_tokens')->default(500000);
                $table->unsignedInteger('used_tokens_current_cycle')->default(0);
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->string('status', 24)->default('active'); // active, expired, suspended
                $table->timestamps();
            });
        }

        // 2. نیازسنجی آموزشی بر اساس شکاف شایستگی (Training Needs & Competency Gaps)
        if (! Schema::hasTable('hrm_training_needs_assessments')) {
            Schema::create('hrm_training_needs_assessments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('hrm_employees')->cascadeOnDelete();
                $table->foreignId('course_id')->nullable()->constrained('hrm_training_courses')->nullOnDelete();
                $table->string('competency_title', 120);
                $table->decimal('required_proficiency_level', 4, 2)->default(4.00); // 1.0 to 5.0
                $table->decimal('current_proficiency_level', 4, 2)->default(2.50);
                $table->decimal('gap_score', 4, 2)->default(1.50);
                $table->string('priority', 20)->default('high'); // low, medium, high, critical
                $table->string('status', 24)->default('identified'); // identified, enrolled, completed
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hrm_training_needs_assessments');
        Schema::dropIfExists('webina_ai_subscriptions');
    }
};
