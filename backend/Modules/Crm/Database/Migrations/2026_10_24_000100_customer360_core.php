<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer 360 — phase 0 (core). Additive + nullable only; safe on existing data.
 */
return new class extends Migration
{
    private function addColumns(string $table, array $defs): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }
        $missing = array_filter($defs, fn ($c) => ! Schema::hasColumn($table, $c), ARRAY_FILTER_USE_KEY);
        if ($missing === []) {
            return;
        }
        Schema::table($table, function (Blueprint $t) use ($missing) {
            foreach ($missing as $def) {
                $def($t);
            }
        });
    }

    public function up(): void
    {
        $this->addColumns('crm_accounts', [
            'phone' => fn (Blueprint $t) => $t->string('phone', 30)->nullable(),
            'mobile' => fn (Blueprint $t) => $t->string('mobile', 30)->nullable(),
            'email' => fn (Blueprint $t) => $t->string('email', 255)->nullable(),
            'socials' => fn (Blueprint $t) => $t->json('socials')->nullable(),
            'tags' => fn (Blueprint $t) => $t->json('tags')->nullable(),
            'source' => fn (Blueprint $t) => $t->string('source', 40)->nullable(),
            'segment' => fn (Blueprint $t) => $t->string('segment', 40)->nullable(),
            'custom_fields' => fn (Blueprint $t) => $t->json('custom_fields')->nullable(),
            'payment_terms_days' => fn (Blueprint $t) => $t->unsignedSmallInteger('payment_terms_days')->nullable(),
            'price_list_id' => fn (Blueprint $t) => $t->unsignedBigInteger('price_list_id')->nullable(),
            'currency' => fn (Blueprint $t) => $t->string('currency', 3)->nullable(),
            'default_discount_pct' => fn (Blueprint $t) => $t->decimal('default_discount_pct', 5, 2)->nullable(),
            'iban' => fn (Blueprint $t) => $t->text('iban')->nullable(), // encrypted cast
            'account_manager_id' => fn (Blueprint $t) => $t->foreignId('account_manager_id')->nullable()->constrained('users')->nullOnDelete(),
            'merged_into_id' => fn (Blueprint $t) => $t->unsignedBigInteger('merged_into_id')->nullable()->index(),
            'last_activity_at' => fn (Blueprint $t) => $t->timestamp('last_activity_at')->nullable()->index(),
            'lifetime_value' => fn (Blueprint $t) => $t->decimal('lifetime_value', 18, 2)->nullable(),
        ]);

        if (! Schema::hasTable('crm_account_addresses')) {
            Schema::create('crm_account_addresses', function (Blueprint $t) {
                $t->id();
                $t->foreignId('account_id')->constrained('crm_accounts')->cascadeOnDelete();
                $t->string('type', 20)->default('billing'); // billing|shipping|office
                $t->string('title', 100)->nullable();
                $t->string('province', 100)->nullable();
                $t->string('city', 100)->nullable();
                $t->text('line')->nullable();
                $t->string('postal_code', 20)->nullable();
                $t->decimal('lat', 10, 7)->nullable();
                $t->decimal('lng', 10, 7)->nullable();
                $t->boolean('is_default')->default(false);
                $t->timestamps();
                $t->index(['account_id', 'type']);
            });
        }

        // Link columns on other modules (nullable + indexed; FKs added in 000200 after orphan cleanup).
        foreach (['docs_contracts', 'docs_files', 'marketplace_basalam_connections'] as $tbl) {
            $this->addColumns($tbl, ['crm_account_id' => fn (Blueprint $t) => $t->unsignedBigInteger('crm_account_id')->nullable()->index()]);
        }

        // Documents v2 (contract 5.1)
        $this->addColumns('crm_customer_documents', [
            'version' => fn (Blueprint $t) => $t->unsignedSmallInteger('version')->default(1),
            'parent_id' => fn (Blueprint $t) => $t->unsignedBigInteger('parent_id')->nullable()->index(),
            'mime' => fn (Blueprint $t) => $t->string('mime', 100)->nullable(),
            'size' => fn (Blueprint $t) => $t->unsignedBigInteger('size')->nullable(),
            'tags' => fn (Blueprint $t) => $t->json('tags')->nullable(),
            'contract_id' => fn (Blueprint $t) => $t->unsignedBigInteger('contract_id')->nullable()->index(),
            'project_id' => fn (Blueprint $t) => $t->unsignedBigInteger('project_id')->nullable()->index(),
            'reviewed_by' => fn (Blueprint $t) => $t->unsignedBigInteger('reviewed_by')->nullable(),
            'reviewed_at' => fn (Blueprint $t) => $t->timestamp('reviewed_at')->nullable(),
        ]);

        // Contracts (contract 5.2)
        $this->addColumns('prj_contracts', [
            'contract_no' => fn (Blueprint $t) => $t->string('contract_no', 40)->nullable()->unique(),
            'start_date' => fn (Blueprint $t) => $t->date('start_date')->nullable(),
            'end_date' => fn (Blueprint $t) => $t->date('end_date')->nullable(),
            'signed_at' => fn (Blueprint $t) => $t->timestamp('signed_at')->nullable(),
            'renewal_type' => fn (Blueprint $t) => $t->string('renewal_type', 10)->nullable(),
            'renewal_notice_days' => fn (Blueprint $t) => $t->unsignedSmallInteger('renewal_notice_days')->default(30),
            'currency' => fn (Blueprint $t) => $t->string('currency', 3)->nullable(),
            'tax_pct' => fn (Blueprint $t) => $t->decimal('tax_pct', 5, 2)->nullable(),
            'deal_id' => fn (Blueprint $t) => $t->unsignedBigInteger('deal_id')->nullable()->index(),
            'signed_document_id' => fn (Blueprint $t) => $t->unsignedBigInteger('signed_document_id')->nullable(),
            'owner_id' => fn (Blueprint $t) => $t->unsignedBigInteger('owner_id')->nullable(),
            'notes' => fn (Blueprint $t) => $t->text('notes')->nullable(),
        ]);

        // Projects (contract 5.3)
        $this->addColumns('prj_projects', [
            'contract_id' => fn (Blueprint $t) => $t->unsignedBigInteger('contract_id')->nullable()->index(),
            'budget' => fn (Blueprint $t) => $t->decimal('budget', 18, 2)->nullable(),
            'progress_pct' => fn (Blueprint $t) => $t->unsignedTinyInteger('progress_pct')->nullable(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_account_addresses');
        $drop = function (string $table, array $cols) {
            if (! Schema::hasTable($table)) {
                return;
            }
            $present = array_values(array_filter($cols, fn ($c) => Schema::hasColumn($table, $c)));
            if ($present) {
                if (in_array('account_manager_id', $present, true)) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropConstrainedForeignId('account_manager_id'));
                    $present = array_values(array_diff($present, ['account_manager_id']));
                }
                if ($present) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropColumn($present));
                }
            }
        };
        $drop('crm_accounts', ['phone', 'mobile', 'email', 'socials', 'tags', 'source', 'segment', 'custom_fields', 'payment_terms_days', 'price_list_id', 'currency', 'default_discount_pct', 'iban', 'account_manager_id', 'merged_into_id', 'last_activity_at', 'lifetime_value']);
        // Link/extension columns on other modules are intentionally kept (non-destructive rollback).
    }
};
