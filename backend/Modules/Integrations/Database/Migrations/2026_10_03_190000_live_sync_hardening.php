<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('int_calendar_accounts', function (Blueprint $table) {
            $table->unsignedSmallInteger('refresh_attempts')->default(0);
            $table->text('last_refresh_error')->nullable();
        });

        Schema::table('int_email_accounts', function (Blueprint $table) {
            $table->string('status', 20)->default('active');
            $table->unsignedSmallInteger('refresh_attempts')->default(0);
            $table->text('last_refresh_error')->nullable();
            $table->json('meta')->nullable();
        });

        Schema::table('int_email_messages', function (Blueprint $table) {
            $table->boolean('is_spam')->default(false);
            $table->unsignedTinyInteger('spam_score')->default(0);
            $table->string('folder', 20)->default('inbox');
            $table->index(['account_id', 'is_spam']);
        });

        Schema::create('int_inbound_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 20);
            $table->foreignId('bridge_id')->nullable()->constrained('int_chat_bridges')->nullOnDelete();
            $table->string('external_id')->nullable();
            $table->json('payload');
            $table->string('status', 20)->default('queued');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('available_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'available_at']);
        });

        Schema::create('int_social_connectors', function (Blueprint $table) {
            $table->id();
            $table->string('network', 32);
            $table->string('name');
            $table->text('access_token')->nullable();
            $table->string('external_id')->nullable();
            $table->boolean('enabled')->default(true);
            $table->string('status', 20)->default('active');
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('int_social_connectors');
        Schema::dropIfExists('int_inbound_events');
        Schema::table('int_email_messages', function (Blueprint $table) {
            $table->dropIndex(['account_id', 'is_spam']);
            $table->dropColumn(['is_spam', 'spam_score', 'folder']);
        });
        Schema::table('int_email_accounts', function (Blueprint $table) {
            $table->dropColumn(['status', 'refresh_attempts', 'last_refresh_error', 'meta']);
        });
        Schema::table('int_calendar_accounts', function (Blueprint $table) {
            $table->dropColumn(['refresh_attempts', 'last_refresh_error']);
        });
    }
};
