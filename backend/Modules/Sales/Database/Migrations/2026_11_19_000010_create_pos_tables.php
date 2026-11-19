<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Real point of sale: registers, cashier sessions (shifts) with cash count,
 * orders with lines and split payments, cash in/out movements and product variants.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('acc_product_variants')) {
            Schema::create('acc_product_variants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained('acc_products')->cascadeOnDelete();
                $table->string('name', 191);
                $table->string('sku', 100)->nullable();
                $table->string('barcode', 100)->nullable()->index();
                $table->decimal('sell_price', 15, 2)->nullable();
                $table->json('attributes')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pos_registers')) {
            Schema::create('pos_registers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->string('name', 160);
                $table->string('code', 32)->nullable();
                $table->unsignedBigInteger('warehouse_id')->nullable();
                $table->decimal('tax_rate', 5, 2)->default(0);
                $table->boolean('tax_inclusive')->default(true);
                $table->boolean('allow_negative_stock')->default(false);
                $table->json('payment_methods')->nullable();
                $table->text('receipt_header')->nullable();
                $table->text('receipt_footer')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pos_sessions')) {
            Schema::create('pos_sessions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('register_id')->constrained('pos_registers')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users');
                $table->string('status', 20)->default('open')->index();
                $table->timestamp('opened_at');
                $table->timestamp('closed_at')->nullable();
                $table->decimal('opening_cash', 18, 2)->default(0);
                $table->decimal('expected_cash', 18, 2)->nullable();
                $table->decimal('counted_cash', 18, 2)->nullable();
                $table->decimal('cash_difference', 18, 2)->nullable();
                $table->json('denominations')->nullable();
                $table->json('totals')->nullable();
                $table->unsignedInteger('z_number')->nullable();
                $table->text('opening_note')->nullable();
                $table->text('closing_note')->nullable();
                $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pos_orders')) {
            Schema::create('pos_orders', function (Blueprint $table) {
                $table->id();
                $table->string('number', 48)->unique();
                $table->string('type', 16)->default('sale'); // sale, refund
                $table->foreignId('session_id')->constrained('pos_sessions');
                $table->foreignId('register_id')->constrained('pos_registers');
                $table->foreignId('user_id')->constrained('users');
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->unsignedBigInteger('customer_id')->nullable()->index();
                $table->string('customer_name', 191)->nullable();
                $table->foreignId('refund_of_id')->nullable()->constrained('pos_orders')->nullOnDelete();
                $table->string('status', 24)->default('completed'); // completed, partially_refunded, refunded
                $table->decimal('subtotal', 18, 2)->default(0);
                $table->decimal('discount_total', 18, 2)->default(0);
                $table->decimal('tax_total', 18, 2)->default(0);
                $table->decimal('total', 18, 2)->default(0);
                $table->decimal('paid_total', 18, 2)->default(0);
                $table->decimal('change_due', 18, 2)->default(0);
                $table->unsignedBigInteger('sales_invoice_id')->nullable();
                $table->unsignedBigInteger('journal_entry_id')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index(['register_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('pos_order_lines')) {
            Schema::create('pos_order_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained('pos_orders')->cascadeOnDelete();
                $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->unsignedBigInteger('variant_id')->nullable();
                $table->foreignId('refund_of_line_id')->nullable()->constrained('pos_order_lines')->nullOnDelete();
                $table->string('name', 255);
                $table->string('barcode', 100)->nullable();
                $table->decimal('quantity', 12, 3);
                $table->decimal('unit_price', 15, 2);
                $table->decimal('discount_amount', 15, 2)->default(0);
                $table->decimal('tax_rate', 5, 2)->default(0);
                $table->decimal('tax_amount', 15, 2)->default(0);
                $table->decimal('line_total', 15, 2);
                $table->decimal('refunded_quantity', 12, 3)->default(0);
                $table->boolean('stock_tracked')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pos_payments')) {
            Schema::create('pos_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained('pos_orders')->cascadeOnDelete();
                $table->foreignId('session_id')->constrained('pos_sessions');
                $table->string('method', 24); // cash, card, transfer, on_account
                $table->decimal('amount', 18, 2);
                $table->string('reference', 100)->nullable();
                $table->timestamps();
                $table->index(['session_id', 'method']);
            });
        }

        if (! Schema::hasTable('pos_cash_movements')) {
            Schema::create('pos_cash_movements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('session_id')->constrained('pos_sessions')->cascadeOnDelete();
                $table->string('type', 8); // in, out
                $table->decimal('amount', 18, 2);
                $table->string('reason', 255)->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        $this->ensurePermissions();
    }

    private function ensurePermissions(): void
    {
        if (! Schema::hasTable('permissions') || ! class_exists(\Spatie\Permission\Models\Permission::class)) {
            return;
        }
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $perms = ['sales.pos.view', 'sales.pos.sell', 'sales.pos.manage'];
        foreach ($perms as $name) {
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        $grants = [
            'system_manager' => $perms,
            'finance_manager' => $perms,
            'sales_consultant' => ['sales.pos.view', 'sales.pos.sell'],
        ];
        foreach ($grants as $roleName => $names) {
            $role = \Spatie\Permission\Models\Role::query()->where('name', $roleName)->where('guard_name', 'web')->first();
            $role?->givePermissionTo($names);
        }
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_cash_movements');
        Schema::dropIfExists('pos_payments');
        Schema::dropIfExists('pos_order_lines');
        Schema::dropIfExists('pos_orders');
        Schema::dropIfExists('pos_sessions');
        Schema::dropIfExists('pos_registers');
        Schema::dropIfExists('acc_product_variants');
    }
};
