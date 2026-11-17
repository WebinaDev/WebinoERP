<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('org_branches')) {
            Schema::create('org_branches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('crm_companies')->cascadeOnDelete();
                $table->string('code', 32)->nullable();
                $table->string('name', 160);
                $table->string('type', 32)->default('branch'); // branch, store, warehouse_hub, regional
                $table->string('phone', 32)->nullable();
                $table->text('address')->nullable();
                $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('default_warehouse_id')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('org_user_branches')) {
            Schema::create('org_user_branches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('branch_id')->constrained('org_branches')->cascadeOnDelete();
                $table->boolean('is_active')->default(false);
                $table->timestamps();
                $table->unique(['user_id', 'branch_id']);
            });
        }

        if (! Schema::hasTable('org_document_sequences')) {
            Schema::create('org_document_sequences', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('crm_companies')->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('org_branches')->nullOnDelete();
                $table->string('document_type', 48); // sales_invoice, acc_invoice, journal_entry, transfer_voucher, work_order, inspection
                $table->string('prefix', 16)->default('');
                $table->unsignedInteger('next_number')->default(1);
                $table->unsignedTinyInteger('padding_zeros')->default(5);
                $table->timestamps();
                $table->unique(['company_id', 'branch_id', 'document_type']);
            });
        }

        if (! Schema::hasTable('org_transfers')) {
            Schema::create('org_transfers', function (Blueprint $table) {
                $table->id();
                $table->string('transfer_number', 48)->unique();
                $table->string('type', 24)->default('inter_branch'); // inter_branch, inter_company
                $table->foreignId('from_company_id')->constrained('crm_companies')->cascadeOnDelete();
                $table->foreignId('to_company_id')->constrained('crm_companies')->cascadeOnDelete();
                $table->foreignId('from_branch_id')->nullable()->constrained('org_branches')->nullOnDelete();
                $table->foreignId('to_branch_id')->nullable()->constrained('org_branches')->nullOnDelete();
                $table->foreignId('from_warehouse_id')->nullable();
                $table->foreignId('to_warehouse_id')->nullable();
                $table->date('transfer_date');
                $table->string('status', 24)->default('draft'); // draft, in_transit, received, cancelled
                $table->json('items')->nullable(); // [{product_id, qty, unit_price, total}]
                $table->decimal('total_amount', 18, 2)->default(0);
                $table->foreignId('journal_entry_id')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('received_at')->nullable();
                $table->timestamps();
            });
        }

        // Add company_id / branch_id scopes across core domain tables if missing
        $tablesToScope = [
            'acc_fiscal_years' => ['company_id'],
            'acc_chart_accounts' => ['company_id'],
            'acc_journal_entries' => ['company_id', 'branch_id'],
            'acc_invoices' => ['company_id', 'branch_id'],
            'acc_receipt_vouchers' => ['company_id', 'branch_id'],
            'acc_cash_accounts' => ['company_id', 'branch_id'],
            'acc_warehouses' => ['company_id', 'branch_id'],
            'scm_warehouses' => ['company_id', 'branch_id'],
            'scm_warehouse_documents' => ['company_id', 'branch_id'],
            'sales_invoices' => ['company_id', 'branch_id'],
            'docs_contracts' => ['company_id', 'branch_id'],
            'hrm_employees' => ['company_id', 'branch_id'],
        ];

        foreach ($tablesToScope as $table => $cols) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $tableBlueprint) use ($table, $cols) {
                    if (in_array('company_id', $cols, true) && ! Schema::hasColumn($table, 'company_id')) {
                        $tableBlueprint->foreignId('company_id')->nullable()->constrained('crm_companies')->nullOnDelete();
                    }
                    if (in_array('branch_id', $cols, true) && ! Schema::hasColumn($table, 'branch_id')) {
                        $tableBlueprint->foreignId('branch_id')->nullable()->constrained('org_branches')->nullOnDelete();
                    }
                });
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('org_transfers');
        Schema::dropIfExists('org_document_sequences');
        Schema::dropIfExists('org_user_branches');
        Schema::dropIfExists('org_branches');
    }
};
