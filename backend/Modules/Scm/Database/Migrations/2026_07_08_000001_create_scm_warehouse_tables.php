<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scm_warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->text('address')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('scm_warehouse_documents', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30);
            $table->foreignId('warehouse_id')->constrained('scm_warehouses')->cascadeOnDelete();
            $table->string('number', 50)->nullable();
            $table->date('document_date')->nullable();
            $table->string('status', 30)->default('draft');
            $table->string('reference', 191)->nullable();
            $table->json('items')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('scm_warehouse_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('scm_warehouses')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('acc_products')->cascadeOnDelete();
            $table->decimal('quantity', 18, 4)->default(0);
            $table->decimal('reorder_point', 18, 4)->nullable();
            $table->timestamps();
            $table->unique(['warehouse_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scm_warehouse_stock');
        Schema::dropIfExists('scm_warehouse_documents');
        Schema::dropIfExists('scm_warehouses');
    }
};
