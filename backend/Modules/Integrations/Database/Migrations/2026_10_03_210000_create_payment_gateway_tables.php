<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_intents', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('idempotency_key', 80)->nullable()->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('domain', 255)->nullable()->index();
            $table->string('payable_type', 40);
            $table->string('payable_id', 64);
            $table->string('mode', 20);
            $table->string('gateway', 32);
            $table->string('currency', 8)->default('IRR');
            $table->unsignedBigInteger('base_amount');
            $table->decimal('fee_percent', 8, 3)->default(0);
            $table->unsignedBigInteger('fee_amount')->default(0);
            $table->unsignedBigInteger('total_amount');
            $table->boolean('fee_applied')->default(false);
            $table->string('status', 24)->default('pending')->index();
            $table->string('authority', 191)->nullable()->index();
            $table->string('provider_ref', 191)->nullable();
            $table->string('provider_tx', 64)->nullable()->index();
            $table->text('redirect_url')->nullable();
            $table->text('return_url')->nullable();
            $table->string('callback_token', 80);
            $table->string('description', 500)->nullable();
            $table->string('mobile', 20)->nullable();
            $table->json('meta')->nullable();
            $table->json('provider_payload')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('side_effects_at')->nullable();
            $table->string('failure_reason', 500)->nullable();
            $table->timestamps();
            $table->index(['payable_type', 'payable_id']);
            $table->index(['gateway', 'status']);
        });

        Schema::create('payment_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_intent_id')->constrained('payment_intents')->cascadeOnDelete();
            $table->string('entry_type', 32);
            $table->bigInteger('amount');
            $table->string('status', 24);
            $table->string('note', 255)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_wallets', function (Blueprint $table) {
            $table->id();
            $table->string('domain', 255)->unique();
            $table->decimal('balance', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('payment_wallet_ledgers', function (Blueprint $table) {
            $table->id();
            $table->string('domain', 255)->index();
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->foreignId('payment_intent_id')->nullable()->constrained('payment_intents')->nullOnDelete();
            $table->string('note', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_wallet_ledgers');
        Schema::dropIfExists('payment_wallets');
        Schema::dropIfExists('payment_ledger_entries');
        Schema::dropIfExists('payment_intents');
    }
};
