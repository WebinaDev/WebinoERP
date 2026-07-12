<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('bale_chat_id', 64)->nullable()->after('phone')->index();
        });

        Schema::create('bale_logs', function (Blueprint $table) {
            $table->id();
            $table->string('level', 20)->default('info')->index();
            $table->string('log_type', 80)->index();
            $table->text('context')->nullable();
            $table->timestamps();
        });

        Schema::create('bale_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('segment_key', 40)->default('newcomer')->index();
            $table->string('variant', 2)->default('A');
            $table->text('message_template');
            $table->string('cta_text')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('scheduled_for')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('bale_campaign_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('bale_campaigns')->cascadeOnDelete();
            $table->string('chat_id', 64)->index();
            $table->string('variant', 2)->default('A');
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('delivered_at')->nullable();
            $table->text('response_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('bale_events', function (Blueprint $table) {
            $table->id();
            $table->string('chat_id', 64)->index();
            $table->string('event_type', 80)->index();
            $table->json('payload')->nullable();
            $table->timestamps();
        });

        Schema::create('bale_leads', function (Blueprint $table) {
            $table->id();
            $table->string('chat_id', 64)->unique();
            $table->string('funnel_stage', 40)->default('new')->index();
            $table->unsignedTinyInteger('score')->default(0);
            $table->timestamp('last_event_at')->nullable();
            $table->unsignedBigInteger('converted_customer_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bale_leads');
        Schema::dropIfExists('bale_events');
        Schema::dropIfExists('bale_campaign_deliveries');
        Schema::dropIfExists('bale_campaigns');
        Schema::dropIfExists('bale_logs');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('bale_chat_id');
        });
    }
};
