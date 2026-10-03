<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hrm_org_positions', function (Blueprint $table) {
            if (! Schema::hasColumn('hrm_org_positions', 'incumbent_employee_id')) {
                $table->foreignId('incumbent_employee_id')->nullable()->constrained('hrm_employees')->nullOnDelete();
            }
        });

        if (! Schema::hasTable('hrm_succession_plans')) {
            Schema::create('hrm_succession_plans', function (Blueprint $table) {
                $table->id();
                $table->foreignId('position_id')->constrained('hrm_org_positions')->cascadeOnDelete();
                $table->foreignId('successor_employee_id')->constrained('hrm_employees')->cascadeOnDelete();
                $table->string('readiness', 20)->default('1_year');
                $table->boolean('is_primary')->default(false);
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->unique(['position_id', 'successor_employee_id']);
            });
        }

        Schema::table('hrm_personnel_documents', function (Blueprint $table) {
            if (! Schema::hasColumn('hrm_personnel_documents', 'document_status')) {
                $table->string('document_status', 30)->default('pending_signature');
            }
            if (! Schema::hasColumn('hrm_personnel_documents', 'signer_name')) {
                $table->string('signer_name', 150)->nullable();
            }
            if (! Schema::hasColumn('hrm_personnel_documents', 'signer_user_id')) {
                $table->unsignedBigInteger('signer_user_id')->nullable();
            }
            if (! Schema::hasColumn('hrm_personnel_documents', 'signed_at')) {
                $table->timestamp('signed_at')->nullable();
            }
            if (! Schema::hasColumn('hrm_personnel_documents', 'signature_path')) {
                $table->string('signature_path', 255)->nullable();
            }
            if (! Schema::hasColumn('hrm_personnel_documents', 'signature_placeholder')) {
                $table->string('signature_placeholder', 80)->default('محل امضا');
            }
            if (! Schema::hasColumn('hrm_personnel_documents', 'audit_log')) {
                $table->json('audit_log')->nullable();
            }
        });

        Schema::table('hrm_employee_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('hrm_employee_profiles', 'sheba')) {
                $table->string('sheba', 34)->nullable();
            }
            if (! Schema::hasColumn('hrm_employee_profiles', 'bank_name')) {
                $table->string('bank_name', 80)->nullable();
            }
            if (! Schema::hasColumn('hrm_employee_profiles', 'account_holder')) {
                $table->string('account_holder', 150)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hrm_succession_plans');
        Schema::table('hrm_org_positions', function (Blueprint $table) {
            if (Schema::hasColumn('hrm_org_positions', 'incumbent_employee_id')) {
                $table->dropConstrainedForeignId('incumbent_employee_id');
            }
        });
        Schema::table('hrm_personnel_documents', function (Blueprint $table) {
            foreach (['audit_log', 'signature_placeholder', 'signature_path', 'signed_at', 'signer_user_id', 'signer_name', 'document_status'] as $col) {
                if (Schema::hasColumn('hrm_personnel_documents', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
        Schema::table('hrm_employee_profiles', function (Blueprint $table) {
            foreach (['account_holder', 'bank_name', 'sheba'] as $col) {
                if (Schema::hasColumn('hrm_employee_profiles', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
