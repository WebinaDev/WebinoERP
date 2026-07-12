<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_hosting_settings', function (Blueprint $table) {
            $table->id();
            $table->string('public_crm_url', 512)->nullable();
            $table->string('git_provider', 32)->nullable();
            $table->string('git_base_url', 512)->nullable();
            $table->text('git_pat')->nullable();
            $table->string('portainer_url', 512)->nullable();
            $table->text('portainer_api_token')->nullable();
            $table->string('portainer_tls_fingerprint', 128)->nullable();
            $table->unsignedSmallInteger('portainer_endpoint_id')->nullable();
            $table->text('git_webhook_secret')->nullable();
            $table->timestamps();
        });

        Schema::create('module_git_sources', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('clone_url', 2048);
            $table->string('auth_type', 32)->default('none');
            $table->string('credential_ref', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('core_infra_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 64);
            $table->string('action', 128);
            $table->string('subject_type', 128)->nullable();
            $table->string('subject_id', 128)->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_infra_audit_logs');
        Schema::dropIfExists('module_git_sources');
        Schema::dropIfExists('core_hosting_settings');
    }
};
