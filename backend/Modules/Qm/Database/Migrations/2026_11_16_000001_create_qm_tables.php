<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('qm_inspection_plans')) {
            Schema::create('qm_inspection_plans', function (Blueprint $table) {
                $table->id();
                $table->string('title', 160);
                $table->string('inspection_type', 32); // incoming, in_process, final, sales_return
                $table->foreignId('product_id')->nullable()->constrained('acc_products')->nullOnDelete();
                $table->json('criteria'); // [{parameter, spec_min, spec_max, target, unit, is_critical}]
                $table->text('instructions')->nullable();
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('qm_lot_batches')) {
            Schema::create('qm_lot_batches', function (Blueprint $table) {
                $table->id();
                $table->string('lot_number', 64)->unique();
                $table->foreignId('product_id')->constrained('acc_products')->cascadeOnDelete();
                $table->decimal('quantity', 18, 4)->default(0);
                $table->date('manufacture_date')->nullable();
                $table->date('expiry_date')->nullable();
                $table->string('status', 24)->default('quarantined'); // quarantined, released, rejected, expired
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('qm_inspections')) {
            Schema::create('qm_inspections', function (Blueprint $table) {
                $table->id();
                $table->string('inspection_number', 48)->unique();
                $table->foreignId('plan_id')->nullable()->constrained('qm_inspection_plans')->nullOnDelete();
                $table->foreignId('product_id')->constrained('acc_products')->cascadeOnDelete();
                $table->foreignId('lot_id')->nullable()->constrained('qm_lot_batches')->nullOnDelete();
                $table->string('inspection_type', 32); // incoming, in_process, final, sales_return
                $table->unsignedBigInteger('reference_id')->nullable(); // SCM warehouse doc, MFG work order, or sales invoice
                $table->decimal('sample_size', 12, 2)->default(1);
                $table->json('results')->nullable(); // [{parameter, measured, pass}]
                $table->string('verdict', 24)->default('pending'); // pending, pass, conditional, fail
                $table->foreignId('inspector_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('inspected_at')->nullable();
                $table->text('remarks')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('qm_ncrs')) {
            Schema::create('qm_ncrs', function (Blueprint $table) {
                $table->id();
                $table->string('ncr_number', 48)->unique();
                $table->foreignId('inspection_id')->nullable()->constrained('qm_inspections')->nullOnDelete();
                $table->foreignId('lot_id')->nullable()->constrained('qm_lot_batches')->nullOnDelete();
                $table->foreignId('product_id')->constrained('acc_products')->cascadeOnDelete();
                $table->string('severity', 20)->default('major'); // minor, major, critical
                $table->string('status', 24)->default('open'); // open, under_investigation, dispositioned, closed
                $table->text('defect_description');
                $table->string('disposition', 32)->nullable(); // scrap, rework, return_to_vendor, accept_as_is
                $table->decimal('affected_quantity', 18, 4)->default(0);
                $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('qm_capas')) {
            Schema::create('qm_capas', function (Blueprint $table) {
                $table->id();
                $table->string('capa_number', 48)->unique();
                $table->foreignId('ncr_id')->nullable()->constrained('qm_ncrs')->nullOnDelete();
                $table->string('type', 20)->default('corrective'); // corrective, preventive
                $table->text('root_cause');
                $table->text('action_plan');
                $table->text('verification_result')->nullable();
                $table->string('status', 24)->default('open'); // open, in_progress, verified, closed
                $table->date('target_date')->nullable();
                $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('qm_capas');
        Schema::dropIfExists('qm_ncrs');
        Schema::dropIfExists('qm_inspections');
        Schema::dropIfExists('qm_lot_batches');
        Schema::dropIfExists('qm_inspection_plans');
    }
};
