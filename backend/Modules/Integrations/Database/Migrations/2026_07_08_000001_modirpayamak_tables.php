<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modirpayamak_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('domain', 255)->unique();
            $table->decimal('balance', 15, 2)->default(0);
            $table->string('default_from', 30)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('modirpayamak_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->decimal('amount', 15, 2);
            $table->unsignedInteger('sms_units')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('modirpayamak_orders', function (Blueprint $table) {
            $table->id();
            $table->string('domain', 255);
            $table->foreignId('package_id')->nullable()->constrained('modirpayamak_packages')->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('authority', 100)->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('ref_id', 100)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('modirpayamak_balance_ledger', function (Blueprint $table) {
            $table->id();
            $table->string('domain', 255);
            $table->string('type', 20); // topup|send|refund|adjust
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->string('reference', 100)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['domain', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modirpayamak_balance_ledger');
        Schema::dropIfExists('modirpayamak_orders');
        Schema::dropIfExists('modirpayamak_packages');
        Schema::dropIfExists('modirpayamak_accounts');
    }
};
