<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Customer 360 Hub completion: uploaded document files + per-site sync state (usage / suspension). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_customer_documents', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_customer_documents', 'file_path')) {
                $table->string('file_path', 500)->nullable();
                $table->string('file_name', 255)->nullable();
                $table->string('file_mime', 120)->nullable();
                $table->unsignedBigInteger('file_size')->nullable();
            }
        });

        if (! Schema::hasTable('crm_customer_site_links')) {
            Schema::create('crm_customer_site_links', function (Blueprint $table) {
                $table->id();
                $table->foreignId('account_id')->constrained('crm_accounts')->cascadeOnDelete();
                $table->unsignedBigInteger('site_provision_id');
                $table->timestamp('usage_synced_at')->nullable();
                $table->unsignedInteger('usage_attempts')->default(0);
                $table->timestamp('usage_next_at')->nullable();
                $table->string('usage_error', 1000)->nullable();
                $table->boolean('suspension_desired')->nullable(); // null = nothing pending
                $table->timestamp('suspension_synced_at')->nullable();
                $table->unsignedInteger('suspension_attempts')->default(0);
                $table->timestamp('suspension_next_at')->nullable();
                $table->string('suspension_error', 1000)->nullable();
                $table->timestamps();
                $table->unique(['account_id', 'site_provision_id'], 'crm_cust_site_link_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_customer_site_links');
        Schema::table('crm_customer_documents', function (Blueprint $table) {
            $table->dropColumn(['file_path', 'file_name', 'file_mime', 'file_size']);
        });
    }
};
