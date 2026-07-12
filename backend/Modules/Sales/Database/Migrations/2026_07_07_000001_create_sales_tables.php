<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_catalog_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sku')->nullable()->unique();
            $table->decimal('price', 15, 2)->default(0);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('sales_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('sales_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number', 50)->unique();
            $table->string('customer_name');
            $table->decimal('total', 15, 2)->default(0);
            $table->string('status', 20)->default('draft');
            $table->date('issue_date')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_invoices');
        Schema::dropIfExists('sales_campaigns');
        Schema::dropIfExists('sales_catalog_items');
    }
};
