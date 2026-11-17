<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Purchase Requests (PR)
        if (! Schema::hasTable('prc_purchase_requests')) {
            Schema::create('prc_purchase_requests', function (Blueprint $table) {
                $table->id();
                $table->string('pr_number', 48)->unique();
                $table->foreignId('company_id')->nullable()->constrained('crm_companies')->nullOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('org_branches')->nullOnDelete();
                $table->string('department', 80)->nullable();
                $table->string('priority', 20)->default('medium'); // low, medium, high, urgent
                $table->string('status', 24)->default('draft'); // draft, submitted, approved, rejected, converted_to_po
                $table->date('required_by_date')->nullable();
                $table->text('reason')->nullable();
                $table->json('items'); // [{product_id, product_name, quantity, estimated_price, notes}]
                $table->decimal('total_estimated_amount', 18, 2)->default(0);
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();
            });
        }

        // 2. Request for Quotation (RFQ)
        if (! Schema::hasTable('prc_rfqs')) {
            Schema::create('prc_rfqs', function (Blueprint $table) {
                $table->id();
                $table->string('rfq_number', 48)->unique();
                $table->foreignId('pr_id')->nullable()->constrained('prc_purchase_requests')->nullOnDelete();
                $table->string('title', 160);
                $table->string('status', 24)->default('open'); // open, evaluating, awarded, closed
                $table->date('deadline_date')->nullable();
                $table->json('vendor_ids')->nullable(); // list of acc_persons (suppliers)
                $table->json('bids')->nullable(); // [{vendor_id, total_amount, delivery_days, terms, is_winner}]
                $table->foreignId('winner_vendor_id')->nullable()->constrained('acc_persons')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        // 3. Purchase Orders (PO)
        if (! Schema::hasTable('prc_purchase_orders')) {
            Schema::create('prc_purchase_orders', function (Blueprint $table) {
                $table->id();
                $table->string('po_number', 48)->unique();
                $table->foreignId('company_id')->nullable()->constrained('crm_companies')->nullOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('org_branches')->nullOnDelete();
                $table->foreignId('vendor_id')->constrained('acc_persons')->cascadeOnDelete();
                $table->foreignId('pr_id')->nullable()->constrained('prc_purchase_requests')->nullOnDelete();
                $table->foreignId('rfq_id')->nullable()->constrained('prc_rfqs')->nullOnDelete();
                $table->date('order_date');
                $table->date('expected_delivery_date')->nullable();
                $table->string('status', 24)->default('draft'); // draft, confirmed, partially_received, received, billed, cancelled
                $table->string('payment_terms', 64)->nullable(); // net30, cash, instalment
                $table->json('items'); // [{product_id, quantity, unit_price, tax_percent, total}]
                $table->decimal('subtotal', 18, 2)->default(0);
                $table->decimal('tax', 18, 2)->default(0);
                $table->decimal('total', 18, 2)->default(0);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        // 4. Goods Receipt Notes (GRN)
        if (! Schema::hasTable('prc_goods_receipts')) {
            Schema::create('prc_goods_receipts', function (Blueprint $table) {
                $table->id();
                $table->string('grn_number', 48)->unique();
                $table->foreignId('po_id')->constrained('prc_purchase_orders')->cascadeOnDelete();
                $table->foreignId('warehouse_id')->constrained('scm_warehouses')->cascadeOnDelete();
                $table->date('receipt_date');
                $table->string('delivery_note_ref', 80)->nullable();
                $table->string('status', 24)->default('received'); // received, inspected, accepted, rejected
                $table->json('items'); // [{product_id, ordered_qty, received_qty, accepted_qty, rejected_qty}]
                $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('prc_goods_receipts');
        Schema::dropIfExists('prc_purchase_orders');
        Schema::dropIfExists('prc_rfqs');
        Schema::dropIfExists('prc_purchase_requests');
    }
};
