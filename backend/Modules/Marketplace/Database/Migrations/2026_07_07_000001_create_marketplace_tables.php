<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('marketplace_products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->decimal('price', 15, 2)->default(0);
            $table->text('description')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('marketplace_categories')->nullOnDelete();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('marketplace_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 50)->unique();
            $table->decimal('total', 15, 2)->default(0);
            $table->string('status', 20)->default('pending');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('marketplace_gitea_settings', function (Blueprint $table) {
            $table->id();
            $table->string('host');
            $table->string('org')->nullable();
            $table->string('token')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_gitea_settings');
        Schema::dropIfExists('marketplace_orders');
        Schema::dropIfExists('marketplace_products');
        Schema::dropIfExists('marketplace_categories');
    }
};
