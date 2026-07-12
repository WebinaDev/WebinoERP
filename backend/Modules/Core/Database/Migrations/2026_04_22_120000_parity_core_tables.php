<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_licenses', function (Blueprint $table) {
            $table->id();
            $table->string('license_key', 191)->unique();
            $table->string('domain', 255)->nullable();
            $table->string('status', 50)->default('active');
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('max_users')->nullable();
            $table->json('meta')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('core_canned_responses', function (Blueprint $table) {
            $table->id();
            $table->string('title', 191);
            $table->text('body');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('core_positions', function (Blueprint $table) {
            $table->id();
            $table->string('title', 191);
            $table->json('permissions')->nullable();
            $table->timestamps();
        });

        Schema::create('core_task_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->string('color', 7)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('core_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 50)->default('info');
            $table->json('data')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_notifications');
        Schema::dropIfExists('core_task_categories');
        Schema::dropIfExists('core_positions');
        Schema::dropIfExists('core_canned_responses');
        Schema::dropIfExists('core_licenses');
    }
};
