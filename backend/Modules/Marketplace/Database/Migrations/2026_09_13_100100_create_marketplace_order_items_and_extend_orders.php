<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('crm_account_id')->nullable()->after('user_id');
            $table->unsignedBigInteger('site_provision_id')->nullable()->after('crm_account_id');
            $table->unsignedBigInteger('license_id')->nullable()->after('site_provision_id');
            $table->string('payment_gateway', 32)->nullable()->after('license_id');
            $table->string('payment_ref', 128)->nullable()->after('payment_gateway');
            $table->timestamp('paid_at')->nullable()->after('payment_ref');
            $table->index(['crm_account_id', 'site_provision_id', 'license_id']);
        });

        Schema::create('marketplace_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('marketplace_orders')->cascadeOnDelete();
            $table->foreignId('module_id')->nullable()->constrained('marketplace_modules')->nullOnDelete();
            $table->string('module_slug', 64);
            $table->unsignedBigInteger('release_id')->nullable();
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();
            $table->index(['module_slug', 'release_id']);
        });

        Schema::create('site_module_installs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_provision_id');
            $table->string('module_slug', 64);
            $table->string('status', 32)->default('pending');
            $table->string('version', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->unique(['site_provision_id', 'module_slug']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_module_installs');
        Schema::dropIfExists('marketplace_order_items');

        Schema::table('marketplace_orders', function (Blueprint $table) {
            $table->dropColumn([
                'crm_account_id',
                'site_provision_id',
                'license_id',
                'payment_gateway',
                'payment_ref',
                'paid_at',
            ]);
        });
    }
};
