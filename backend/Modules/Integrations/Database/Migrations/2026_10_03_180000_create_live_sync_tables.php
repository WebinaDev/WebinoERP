<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('int_calendar_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('provider', 20);
            $table->string('email')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('calendar_id')->default('primary');
            $table->string('sync_token')->nullable();
            $table->string('webhook_channel_id')->nullable();
            $table->text('webhook_secret')->nullable();
            $table->timestamp('webhook_expires_at')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamp('last_synced_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'provider', 'calendar_id']);
        });

        Schema::create('int_calendar_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('int_calendar_accounts')->cascadeOnDelete();
            $table->string('external_id');
            $table->string('local_type', 20);
            $table->unsignedBigInteger('local_id');
            $table->string('etag')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->unique(['account_id', 'external_id']);
        });

        Schema::create('int_chat_bridges', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 20);
            $table->string('name');
            $table->text('bot_token')->nullable();
            $table->text('webhook_secret')->nullable();
            $table->string('channel_id')->nullable();
            $table->boolean('inbound_commands')->default(false);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('int_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bridge_id')->constrained('int_chat_bridges')->cascadeOnDelete();
            $table->string('direction', 10);
            $table->string('external_id')->nullable();
            $table->string('chat_id')->nullable();
            $table->text('body');
            $table->json('payload')->nullable();
            $table->timestamps();
        });

        Schema::create('int_email_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('provider', 20);
            $table->string('email');
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('imap_host')->nullable();
            $table->unsignedSmallInteger('imap_port')->nullable();
            $table->string('smtp_host')->nullable();
            $table->unsignedSmallInteger('smtp_port')->nullable();
            $table->text('username')->nullable();
            $table->text('password')->nullable();
            $table->string('encryption', 10)->nullable();
            $table->timestamps();
        });

        Schema::create('int_email_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('int_email_accounts')->cascadeOnDelete();
            $table->string('external_id')->nullable();
            $table->string('thread_key')->nullable();
            $table->string('direction', 10)->default('in');
            $table->string('from_email')->nullable();
            $table->string('to_email')->nullable();
            $table->string('subject')->nullable();
            $table->longText('body')->nullable();
            $table->json('attachments')->nullable();
            $table->unsignedBigInteger('crm_account_id')->nullable();
            $table->unsignedBigInteger('activity_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['account_id', 'thread_key']);
        });

        Schema::create('int_sms_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('event_key')->default('*');
            $table->string('min_priority', 20)->default('high');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('int_channel_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 20);
            $table->string('event_key')->nullable();
            $table->string('priority', 20);
            $table->string('status', 30);
            $table->string('reason')->nullable();
            $table->text('body')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('int_channel_deliveries');
        Schema::dropIfExists('int_sms_rules');
        Schema::dropIfExists('int_email_messages');
        Schema::dropIfExists('int_email_accounts');
        Schema::dropIfExists('int_chat_messages');
        Schema::dropIfExists('int_chat_bridges');
        Schema::dropIfExists('int_calendar_links');
        Schema::dropIfExists('int_calendar_accounts');
    }
};
