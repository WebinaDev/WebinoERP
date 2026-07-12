<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mfg_boms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('version', 50)->default('1.0');
            $table->string('status', 20)->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['product_id', 'status']);
        });

        Schema::create('mfg_bom_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bom_id')->constrained('mfg_boms')->cascadeOnDelete();
            $table->unsignedBigInteger('component_product_id');
            $table->decimal('quantity', 14, 4)->default(1);
            $table->string('unit', 20)->nullable();
            $table->decimal('scrap_percent', 5, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('mfg_work_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bom_id')->nullable()->constrained('mfg_boms')->nullOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->decimal('qty_planned', 14, 4)->default(1);
            $table->decimal('qty_produced', 14, 4)->default(0);
            $table->string('status', 20)->default('draft');
            $table->timestamp('due_at')->nullable();
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->timestamps();
            $table->index('status');
        });

        Schema::create('mfg_work_order_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('mfg_work_orders')->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence')->default(1);
            $table->string('name');
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->timestamps();
        });

        Schema::create('mfg_quality_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('mfg_work_orders')->cascadeOnDelete();
            $table->string('type', 30)->default('final');
            $table->string('status', 20)->default('open');
            $table->string('result', 20)->nullable();
            $table->timestamp('inspected_at')->nullable();
            $table->timestamps();
        });

        Schema::create('mfg_quality_check_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_id')->constrained('mfg_quality_inspections')->cascadeOnDelete();
            $table->string('criterion');
            $table->string('measured_value')->nullable();
            $table->decimal('spec_min', 14, 4)->nullable();
            $table->decimal('spec_max', 14, 4)->nullable();
            $table->boolean('passed')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mfg_quality_check_items');
        Schema::dropIfExists('mfg_quality_inspections');
        Schema::dropIfExists('mfg_work_order_operations');
        Schema::dropIfExists('mfg_work_orders');
        Schema::dropIfExists('mfg_bom_lines');
        Schema::dropIfExists('mfg_boms');
    }
};
