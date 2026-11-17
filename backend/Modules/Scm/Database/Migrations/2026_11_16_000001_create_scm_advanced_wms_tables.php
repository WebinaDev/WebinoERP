<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. مکان‌های داخلی انبار (Aisle / Rack / Shelf / Bin)
        if (! Schema::hasTable('scm_warehouse_locations')) {
            Schema::create('scm_warehouse_locations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('warehouse_id')->constrained('scm_warehouses')->cascadeOnDelete();
                $table->string('name', 100);
                $table->string('barcode', 64)->nullable()->index();
                $table->string('location_type', 30)->default('storage'); // storage, receiving, picking, qc, damaged
                $table->string('zone', 50)->nullable();
                $table->string('aisle', 30)->nullable();
                $table->string('rack', 30)->nullable();
                $table->string('bin', 30)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 2. اقلام نرمال‌شده اسناد انبار (جایگزین فیلد JSON)
        if (! Schema::hasTable('scm_warehouse_document_lines')) {
            Schema::create('scm_warehouse_document_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('document_id')->constrained('scm_warehouse_documents')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('acc_products')->cascadeOnDelete();
                $table->foreignId('location_id')->nullable()->constrained('scm_warehouse_locations')->nullOnDelete();
                $table->string('lot_batch_no', 64)->nullable()->index();
                $table->string('serial_no', 64)->nullable()->index();
                $table->date('expiry_date')->nullable();
                $table->decimal('quantity', 18, 4);
                $table->string('uom', 30)->default('unit');
                $table->decimal('unit_cost', 18, 2)->default(0);
                $table->decimal('total_cost', 18, 2)->default(0);
                $table->timestamps();
            });
        }

        // 3. دفتر کل حرکات انبار (Immutable Stock Ledger)
        if (! Schema::hasTable('scm_stock_ledger')) {
            Schema::create('scm_stock_ledger', function (Blueprint $table) {
                $table->id();
                $table->foreignId('warehouse_id')->constrained('scm_warehouses')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('acc_products')->cascadeOnDelete();
                $table->foreignId('location_id')->nullable()->constrained('scm_warehouse_locations')->nullOnDelete();
                $table->foreignId('document_id')->nullable()->constrained('scm_warehouse_documents')->nullOnDelete();
                $table->string('movement_type', 40); // inbound_receipt, outbound_delivery, transfer_in, transfer_out, adjustment
                $table->decimal('quantity_change', 18, 4); // مثبت برای ورود، منفی برای خروج
                $table->decimal('quantity_after', 18, 4); // موجودی لحظه‌ای پس از رویداد
                $table->decimal('unit_cost', 18, 2)->default(0);
                $table->string('lot_batch_no', 64)->nullable();
                $table->string('serial_no', 64)->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('recorded_at')->useCurrent();
                $table->timestamps();

                $table->index(['warehouse_id', 'product_id', 'recorded_at']);
            });
        }

        // 4. رزرو موجودی کالا برای سفارش‌های فروش، تولید و پروژه‌ها
        if (! Schema::hasTable('scm_stock_reservations')) {
            Schema::create('scm_stock_reservations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('warehouse_id')->constrained('scm_warehouses')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('acc_products')->cascadeOnDelete();
                $table->decimal('quantity_reserved', 18, 4);
                $table->string('reservation_source', 40); // sales_order, work_order, project
                $table->string('reference_no', 100);
                $table->string('status', 30)->default('active'); // active, fulfilled, cancelled
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('scm_stock_reservations');
        Schema::dropIfExists('scm_stock_ledger');
        Schema::dropIfExists('scm_warehouse_document_lines');
        Schema::dropIfExists('scm_warehouse_locations');
    }
};
