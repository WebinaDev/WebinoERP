<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. ارتقای جدول قراردادها (Docs Contracts) از یادداشت متنی به سند حقوقی رسمی
        Schema::table('docs_contracts', function (Blueprint $table) {
            if (! Schema::hasColumn('docs_contracts', 'contract_number')) {
                $table->string('contract_number', 50)->nullable()->unique()->after('id');
            }
            if (! Schema::hasColumn('docs_contracts', 'crm_deal_id')) {
                $table->unsignedBigInteger('crm_deal_id')->nullable()->index()->after('contract_number');
            }
            if (! Schema::hasColumn('docs_contracts', 'crm_customer_id')) {
                $table->unsignedBigInteger('crm_customer_id')->nullable()->index()->after('crm_deal_id');
            }
            if (! Schema::hasColumn('docs_contracts', 'project_id')) {
                $table->unsignedBigInteger('project_id')->nullable()->index()->after('crm_customer_id');
            }
            if (! Schema::hasColumn('docs_contracts', 'total_amount')) {
                $table->decimal('total_amount', 15, 2)->default(0)->after('status');
            }
            if (! Schema::hasColumn('docs_contracts', 'currency')) {
                $table->string('currency', 10)->default('IRR')->after('total_amount');
            }
            if (! Schema::hasColumn('docs_contracts', 'start_date')) {
                $table->date('start_date')->nullable()->after('currency');
            }
            if (! Schema::hasColumn('docs_contracts', 'end_date')) {
                $table->date('end_date')->nullable()->after('start_date');
            }
            if (! Schema::hasColumn('docs_contracts', 'version')) {
                $table->integer('version')->default(1)->after('end_date');
            }
            if (! Schema::hasColumn('docs_contracts', 'is_locked')) {
                $table->boolean('is_locked')->default(false)->after('version');
            }
            if (! Schema::hasColumn('docs_contracts', 'cancelled_reason')) {
                $table->text('cancelled_reason')->nullable()->after('is_locked');
            }
        });

        // 2. جدول طرف‌های قرارداد (Contract Parties)
        if (! Schema::hasTable('docs_contract_parties')) {
            Schema::create('docs_contract_parties', function (Blueprint $table) {
                $table->id();
                $table->foreignId('contract_id')->constrained('docs_contracts')->cascadeOnDelete();
                $table->string('role', 50)->default('second_party'); // first_party (مجری/کارفرما), second_party (مشتری), guarantor (ضامن)
                $table->string('name', 191);
                $table->string('national_id', 20)->nullable(); // شناسه ملی یا کد ملی
                $table->string('economic_code', 30)->nullable();
                $table->string('registration_number', 50)->nullable();
                $table->string('signatory_name', 150)->nullable(); // نام و سمت امضاکننده مجاز
                $table->string('phone', 30)->nullable();
                $table->string('email', 100)->nullable();
                $table->text('address')->nullable();
                $table->string('postal_code', 20)->nullable();
                $table->timestamps();

                $table->index(['contract_id', 'role']);
            });
        }

        // 3. جدول اقساط و تعهدات مالی ساختاریافته (Contract Installments)
        if (! Schema::hasTable('docs_contract_installments')) {
            Schema::create('docs_contract_installments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('contract_id')->constrained('docs_contracts')->cascadeOnDelete();
                $table->string('title', 150); // e.g. پیش‌پرداخت، قسط اول تحویل فاز ۱
                $table->decimal('amount', 15, 2);
                $table->date('due_date');
                $table->string('status', 30)->default('pending'); // pending, invoiced, paid, overdue
                $table->unsignedBigInteger('sales_invoice_id')->nullable()->index();
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();

                $table->index(['contract_id', 'due_date', 'status']);
            });
        }

        // 4. جدول پاکت‌های امضای دیجیتال و لاگ‌های الکترونیکی (E-Signature Envelopes & Audit)
        if (! Schema::hasTable('docs_signature_envelopes')) {
            Schema::create('docs_signature_envelopes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('contract_id')->constrained('docs_contracts')->cascadeOnDelete();
                $table->string('envelope_token', 64)->unique();
                $table->string('signer_name', 150);
                $table->string('signer_mobile', 30);
                $table->string('otp_code_hash', 255)->nullable();
                $table->timestamp('otp_expires_at')->nullable();
                $table->string('status', 30)->default('pending'); // pending, verified, signed, rejected
                $table->string('signed_ip', 45)->nullable();
                $table->text('signed_user_agent')->nullable();
                $table->string('digital_certificate_hash', 64)->nullable(); // SHA-256 گواهی امضا
                $table->timestamp('signed_at')->nullable();
                $table->timestamps();

                $table->index(['contract_id', 'status']);
            });
        }

        // 5. جدول متمم‌ها و الحاقیه‌های قرارداد (Contract Addenda)
        if (! Schema::hasTable('docs_contract_addenda')) {
            Schema::create('docs_contract_addenda', function (Blueprint $table) {
                $table->id();
                $table->foreignId('contract_id')->constrained('docs_contracts')->cascadeOnDelete();
                $table->string('addendum_number', 50)->unique();
                $table->string('title', 255);
                $table->string('type', 50); // amount_change, extension, scope_change, mutual_termination
                $table->decimal('amount_delta', 15, 2)->default(0);
                $table->date('new_end_date')->nullable();
                $table->text('terms_and_body');
                $table->string('status', 30)->default('active'); // draft, active, cancelled
                $table->timestamp('effective_date')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('docs_contract_addenda');
        Schema::dropIfExists('docs_signature_envelopes');
        Schema::dropIfExists('docs_contract_installments');
        Schema::dropIfExists('docs_contract_parties');

        Schema::table('docs_contracts', function (Blueprint $table) {
            $table->dropColumn([
                'contract_number',
                'crm_deal_id',
                'crm_customer_id',
                'project_id',
                'total_amount',
                'currency',
                'start_date',
                'end_date',
                'version',
                'is_locked',
                'cancelled_reason',
            ]);
        });
    }
};
