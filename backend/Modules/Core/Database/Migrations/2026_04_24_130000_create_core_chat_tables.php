<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_chat_channels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191)->nullable();
            $table->string('type', 20)->default('public')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('core_chat_channel_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained('core_chat_channels')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 20)->default('member');
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamps();
            $table->unique(['channel_id', 'user_id']);
        });

        Schema::create('core_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained('core_chat_channels')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reply_to')->nullable()->constrained('core_chat_messages')->nullOnDelete();
            $table->text('body');
            $table->json('attachments')->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('core_chat_read_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained('core_chat_channels')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('last_read_message_id')->nullable()->constrained('core_chat_messages')->nullOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->unique(['channel_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_chat_read_receipts');
        Schema::dropIfExists('core_chat_messages');
        Schema::dropIfExists('core_chat_channel_members');
        Schema::dropIfExists('core_chat_channels');
    }
};
