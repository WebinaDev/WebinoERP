<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Idempotency ledger for Customer 360 automations: one hit per (rule, account, subject, step).
        if (! Schema::hasTable('crm_automation_hits')) {
            Schema::create('crm_automation_hits', function (Blueprint $t) {
                $t->id();
                $t->string('rule', 40);
                $t->unsignedBigInteger('account_id')->index();
                $t->string('subject', 120);
                $t->string('step', 40)->default('');
                $t->json('meta')->nullable();
                $t->timestamp('created_at')->nullable();
                $t->unique(['rule', 'account_id', 'subject', 'step'], 'crm_auto_hits_unique');
            });
        }
        // "Not a duplicate" decisions for the duplicate finder (pair stored low id first).
        if (! Schema::hasTable('crm_duplicate_dismissals')) {
            Schema::create('crm_duplicate_dismissals', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('account_a_id');
                $t->unsignedBigInteger('account_b_id');
                $t->foreignId('dismissed_by')->nullable()->constrained('users')->nullOnDelete();
                $t->timestamps();
                $t->unique(['account_a_id', 'account_b_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_duplicate_dismissals');
        Schema::dropIfExists('crm_automation_hits');
    }
};
