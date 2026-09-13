<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_invoices', 'invoice_number')) {
                $table->string('invoice_number', 50)->nullable()->after('number');
            }
            if (! Schema::hasColumn('sales_invoices', 'customer_id')) {
                $table->unsignedBigInteger('customer_id')->nullable()->after('customer_name');
            }
            if (! Schema::hasColumn('sales_invoices', 'project_id')) {
                $table->unsignedBigInteger('project_id')->nullable()->after('customer_id');
            }
            if (! Schema::hasColumn('sales_invoices', 'project_title')) {
                $table->string('project_title')->nullable()->after('project_id');
            }
            if (! Schema::hasColumn('sales_invoices', 'payment_method')) {
                $table->text('payment_method')->nullable()->after('issue_date');
            }
            if (! Schema::hasColumn('sales_invoices', 'items')) {
                $table->json('items')->nullable()->after('payment_method');
            }
            if (! Schema::hasColumn('sales_invoices', 'subtotal')) {
                $table->decimal('subtotal', 15, 2)->default(0)->after('items');
            }
            if (! Schema::hasColumn('sales_invoices', 'discount')) {
                $table->decimal('discount', 15, 2)->default(0)->after('subtotal');
            }
            if (! Schema::hasColumn('sales_invoices', 'notes')) {
                $table->text('notes')->nullable()->after('discount');
            }
        });

        Schema::table('sales_campaigns', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_campaigns', 'budget')) {
                $table->decimal('budget', 15, 2)->default(0)->after('channel');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table) {
            $cols = array_filter(
                ['invoice_number', 'customer_id', 'project_id', 'project_title', 'payment_method', 'items', 'subtotal', 'discount', 'notes'],
                fn ($c) => Schema::hasColumn('sales_invoices', $c)
            );
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });

        Schema::table('sales_campaigns', function (Blueprint $table) {
            if (Schema::hasColumn('sales_campaigns', 'budget')) {
                $table->dropColumn('budget');
            }
        });
    }
};
