<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prj_forms', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('title', 255);
            $table->json('fields');
            $table->string('success_message', 500)->nullable();
            $table->json('notify_emails')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('prj_form_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('prj_forms')->cascadeOnDelete();
            $table->json('data');
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->foreignId('converted_lead_id')->nullable()->constrained('crm_leads')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prj_form_submissions');
        Schema::dropIfExists('prj_forms');
    }
};
