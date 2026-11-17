<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. ارتقای جدول فاکتورهای فروش برای Order-to-Cash و اتصال به CRM و مودیان
        Schema::table('sales_invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_invoices', 'crm_deal_id')) {
                $table->unsignedBigInteger('crm_deal_id')->nullable()->index()->after('project_id');
            }
            if (! Schema::hasColumn('sales_invoices', 'sales_order_id')) {
                $table->unsignedBigInteger('sales_order_id')->nullable()->index()->after('crm_deal_id');
            }
            if (! Schema::hasColumn('sales_invoices', 'due_date')) {
                $table->date('due_date')->nullable()->after('issue_date');
            }
            if (! Schema::hasColumn('sales_invoices', 'tax_amount')) {
                $table->decimal('tax_amount', 15, 2)->default(0)->after('discount');
            }
            if (! Schema::hasColumn('sales_invoices', 'paid_amount')) {
                $table->decimal('paid_amount', 15, 2)->default(0)->after('total');
            }
            if (! Schema::hasColumn('sales_invoices', 'payment_status')) {
                $table->string('payment_status', 30)->default('unpaid')->after('paid_amount'); // unpaid, partial, paid, overdue
            }
            if (! Schema::hasColumn('sales_invoices', 'tax_system_id')) {
                $table->string('tax_system_id', 50)->nullable()->index()->after('payment_status'); // شناسه مالیاتی سامانه مودیان
            }
        });

        // 2. جدول پیش‌فاکتورهای رسمی و سفارش‌های فروش (Sales Quotes & B2B Orders)
        if (! Schema::hasTable('sales_quotes')) {
            Schema::create('sales_quotes', function (Blueprint $table) {
                $table->id();
                $table->string('quote_number', 50)->unique();
                $table->unsignedBigInteger('customer_id')->nullable()->index();
                $table->string('customer_name', 191);
                $table->unsignedBigInteger('crm_deal_id')->nullable()->index();
                $table->date('issue_date');
                $table->date('valid_until');
                $table->decimal('subtotal', 15, 2)->default(0);
                $table->decimal('discount_amount', 15, 2)->default(0);
                $table->decimal('tax_amount', 15, 2)->default(0);
                $table->decimal('total_amount', 15, 2)->default(0);
                $table->string('status', 30)->default('draft'); // draft, sent, accepted, rejected, converted
                $table->text('terms_and_conditions')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        // 3. جدول اقلام نرمال‌شده فاکتورها (Normalized Invoice Lines)
        if (! Schema::hasTable('sales_invoice_lines')) {
            Schema::create('sales_invoice_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('invoice_id')->constrained('sales_invoices')->cascadeOnDelete();
                $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->string('item_name', 255);
                $table->decimal('quantity', 12, 3)->default(1);
                $table->string('uom', 50)->default('unit');
                $table->decimal('unit_price', 15, 2)->default(0);
                $table->decimal('discount_rate', 5, 2)->default(0);
                $table->decimal('discount_amount', 15, 2)->default(0);
                $table->decimal('tax_rate', 5, 2)->default(10); // 10% VAT
                $table->decimal('tax_amount', 15, 2)->default(0);
                $table->decimal('line_total', 15, 2)->default(0);
                $table->string('notes', 255)->nullable();
                $table->timestamps();

                $table->index(['invoice_id', 'product_id']);
            });
        }

        // 4. جدول اقلام نرمال‌شده پیش‌فاکتور (Normalized Quote Lines)
        if (! Schema::hasTable('sales_quote_lines')) {
            Schema::create('sales_quote_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('quote_id')->constrained('sales_quotes')->cascadeOnDelete();
                $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->string('item_name', 255);
                $table->decimal('quantity', 12, 3)->default(1);
                $table->string('uom', 50)->default('unit');
                $table->decimal('unit_price', 15, 2)->default(0);
                $table->decimal('discount_rate', 5, 2)->default(0);
                $table->decimal('discount_amount', 15, 2)->default(0);
                $table->decimal('tax_rate', 5, 2)->default(10);
                $table->decimal('tax_amount', 15, 2)->default(0);
                $table->decimal('line_total', 15, 2)->default(0);
                $table->timestamps();
            });
        }

        // 5. جدول ردیابی رشد مشترک و تبدیل‌های بازاریابی به فروش (Marketing-to-Sales Growth Attribution)
        if (! Schema::hasTable('marketing_growth_attributions')) {
            Schema::create('marketing_growth_attributions', function (Blueprint $table) {
                $table->id();
                $table->string('campaign_source', 100)->nullable()->index(); // google, linkedin, organic
                $table->string('utm_campaign', 100)->nullable()->index();
                $table->string('utm_medium', 100)->nullable();
                $table->string('landing_page_url', 255)->nullable();
                $table->unsignedBigInteger('crm_lead_id')->nullable()->index();
                $table->unsignedBigInteger('crm_deal_id')->nullable()->index();
                $table->unsignedBigInteger('sales_quote_id')->nullable()->index();
                $table->unsignedBigInteger('sales_invoice_id')->nullable()->index();
                $table->decimal('realized_revenue', 15, 2)->default(0);
                $table->timestamp('converted_at')->nullable();
                $table->timestamps();

                $table->index(['utm_campaign', 'converted_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_growth_attributions');
        Schema::dropIfExists('sales_quote_lines');
        Schema::dropIfExists('sales_invoice_lines');
        Schema::dropIfExists('sales_quotes');

        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->dropColumn([
                'crm_deal_id',
                'sales_order_id',
                'due_date',
                'tax_amount',
                'paid_amount',
                'payment_status',
                'tax_system_id',
            ]);
        });
    }
};
