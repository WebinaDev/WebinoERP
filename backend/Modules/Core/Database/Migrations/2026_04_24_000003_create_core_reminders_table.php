<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->nullableMorphs('remindable');
            $table->string('title', 191);
            $table->text('body')->nullable();
            $table->string('channel', 20)->default('in_app');
            $table->json('payload')->nullable();
            $table->timestamp('remind_at')->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('snoozed_until')->nullable()->index();
            $table->timestamp('dismissed_at')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'remind_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_reminders');
    }
};
