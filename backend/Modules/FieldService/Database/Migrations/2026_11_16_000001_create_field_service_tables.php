<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fs_install_bases')) {
            Schema::create('fs_install_bases', function (Blueprint $table) {
                $table->id();
                $table->string('serial_number', 64)->unique();
                $table->string('device_model', 120);
                $table->foreignId('account_id')->nullable()->constrained('crm_accounts')->nullOnDelete();
                $table->foreignId('contact_id')->nullable()->constrained('crm_contacts')->nullOnDelete();
                $table->foreignId('product_id')->nullable()->constrained('acc_products')->nullOnDelete();
                $table->date('install_date')->nullable();
                $table->date('warranty_start')->nullable();
                $table->date('warranty_end')->nullable();
                $table->string('status', 24)->default('active'); // active, decommissioned, in_repair
                $table->text('installation_address')->nullable();
                $table->json('specs')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('fs_technicians')) {
            Schema::create('fs_technicians', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('employee_id')->nullable()->constrained('hrm_employees')->nullOnDelete();
                $table->json('skills')->nullable(); // mechanical, electrical, software
                $table->string('zone', 80)->nullable();
                $table->string('phone', 32)->nullable();
                $table->boolean('is_available')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('fs_service_orders')) {
            Schema::create('fs_service_orders', function (Blueprint $table) {
                $table->id();
                $table->string('order_number', 48)->unique();
                $table->foreignId('install_base_id')->nullable()->constrained('fs_install_bases')->nullOnDelete();
                $table->foreignId('account_id')->nullable()->constrained('crm_accounts')->nullOnDelete();
                $table->foreignId('ticket_id')->nullable()->constrained('prj_tickets')->nullOnDelete();
                $table->foreignId('technician_id')->nullable()->constrained('fs_technicians')->nullOnDelete();
                $table->string('priority', 20)->default('medium'); // low, medium, high, critical
                $table->string('status', 24)->default('requested'); // requested, dispatched, in_progress, completed, cancelled
                $table->dateTime('scheduled_at')->nullable();
                $table->dateTime('dispatched_at')->nullable();
                $table->dateTime('started_at')->nullable();
                $table->dateTime('completed_at')->nullable();
                $table->dateTime('sla_response_due')->nullable();
                $table->dateTime('sla_resolution_due')->nullable();
                $table->boolean('sla_response_breached')->default(false);
                $table->boolean('sla_resolution_breached')->default(false);
                $table->text('problem_description')->nullable();
                $table->text('resolution_notes')->nullable();
                $table->decimal('labor_cost', 18, 2)->default(0);
                $table->decimal('parts_cost', 18, 2)->default(0);
                $table->decimal('total_cost', 18, 2)->default(0);
                $table->foreignId('sales_invoice_id')->nullable()->constrained('sales_invoices')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('fs_spare_parts_used')) {
            Schema::create('fs_spare_parts_used', function (Blueprint $table) {
                $table->id();
                $table->foreignId('service_order_id')->constrained('fs_service_orders')->cascadeOnDelete();
                $table->foreignId('warehouse_id')->nullable()->constrained('scm_warehouses')->nullOnDelete();
                $table->foreignId('product_id')->constrained('acc_products')->cascadeOnDelete();
                $table->decimal('quantity', 12, 2)->default(1);
                $table->decimal('unit_cost', 18, 2)->default(0);
                $table->decimal('total_cost', 18, 2)->default(0);
                $table->boolean('is_warranty_covered')->default(false);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fs_spare_parts_used');
        Schema::dropIfExists('fs_service_orders');
        Schema::dropIfExists('fs_technicians');
        Schema::dropIfExists('fs_install_bases');
    }
};
