<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Customer 360 phase 2: unified cross-module customer timeline. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('crm_accounts', 'activities_backfilled_at')) {
            Schema::table('crm_accounts', fn (Blueprint $t) => $t->timestamp('activities_backfilled_at')->nullable());
        }
        if (Schema::hasTable('crm_customer_activities')) {
            return;
        }
        Schema::create('crm_customer_activities', function (Blueprint $t) {
            $t->id();
            $t->foreignId('account_id')->constrained('crm_accounts')->cascadeOnDelete();
            $t->timestamp('occurred_at')->index();
            $t->string('module', 30)->index();
            $t->string('type', 60)->index();
            $t->string('subject_type', 60)->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->string('title', 255);
            $t->json('meta')->nullable();
            $t->unsignedBigInteger('actor_id')->nullable();
            $t->string('visibility_section', 30)->nullable();
            // "{subject_type}:{subject_id}:{type}" — makes listeners and the backfill idempotent.
            $t->string('dedupe_key', 191)->nullable()->unique();
            $t->timestamps();
            $t->index(['account_id', 'occurred_at', 'id'], 'crm_cust_act_account_time');
            $t->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_customer_activities');
        if (Schema::hasColumn('crm_accounts', 'activities_backfilled_at')) {
            Schema::table('crm_accounts', fn (Blueprint $t) => $t->dropColumn('activities_backfilled_at'));
        }
    }
};
