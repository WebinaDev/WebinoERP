<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dev_gateways')) {
            Schema::create('dev_gateways', function (Blueprint $table) {
                $table->id();
                $table->string('gateway_key', 64)->unique();
                $table->string('name', 160);
                $table->string('os_platform', 32); // windows, linux, macos, android, ios
                $table->foreignId('company_id')->nullable()->constrained('crm_companies')->nullOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('org_branches')->nullOnDelete();
                $table->foreignId('warehouse_id')->nullable()->constrained('scm_warehouses')->nullOnDelete();
                $table->string('ip_address', 45)->nullable();
                $table->string('status', 20)->default('online'); // online, offline, error
                $table->timestamp('last_heartbeat_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('dev_peripherals')) {
            Schema::create('dev_peripherals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('gateway_id')->constrained('dev_gateways')->cascadeOnDelete();
                $table->string('device_type', 32); // label_printer, receipt_printer, barcode_scanner, scale, pos_terminal
                $table->string('name', 160);
                $table->string('model', 120)->nullable();
                $table->string('connection_type', 32)->default('usb'); // usb, network, serial, bluetooth
                $table->string('port_identifier', 80)->nullable(); // COM3, /dev/ttyUSB0, 192.168.1.50:9100
                $table->json('config')->nullable(); // baudrate, paper_width, dpi
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('dev_print_jobs')) {
            Schema::create('dev_print_jobs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('peripheral_id')->constrained('dev_peripherals')->cascadeOnDelete();
                $table->string('job_type', 32); // barcode_label, receipt, packing_slip, shipping_label
                $table->text('payload'); // ZPL, ESC/POS, HTML, or raw text
                $table->string('status', 20)->default('pending'); // pending, printing, printed, failed
                $table->text('error_message')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dev_print_jobs');
        Schema::dropIfExists('dev_peripherals');
        Schema::dropIfExists('dev_gateways');
    }
};
