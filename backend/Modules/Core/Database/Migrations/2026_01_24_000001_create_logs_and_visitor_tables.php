<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_system_logs', function (Blueprint $table) {
            $table->id();
            $table->string('level', 20)->default('info')->index();
            $table->string('channel', 50)->nullable()->index();
            $table->text('message');
            $table->json('context')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('core_visitor_events', function (Blueprint $table) {
            $table->id();
            $table->string('path', 500)->nullable()->index();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('visited_at')->useCurrent();
        });

        Schema::create('prj_task_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('prj_tasks')->cascadeOnDelete();
            $table->string('disk', 50)->default('local');
            $table->string('path', 500);
            $table->string('original_name', 255)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prj_task_attachments');
        Schema::dropIfExists('core_visitor_events');
        Schema::dropIfExists('core_system_logs');
    }
};
