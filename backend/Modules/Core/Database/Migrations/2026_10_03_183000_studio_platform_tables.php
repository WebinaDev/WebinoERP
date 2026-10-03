<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_workflows', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('trigger_key', 64)->default('manual');
            $table->json('graph');
            $table->string('status', 20)->default('draft');
            $table->timestamps();
        });

        Schema::create('core_workflow_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('core_workflows')->cascadeOnDelete();
            $table->string('status', 20)->default('done');
            $table->json('context')->nullable();
            $table->json('log')->nullable();
            $table->timestamps();
        });

        Schema::create('core_bi_reports', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('source', 40);
            $table->json('columns');
            $table->json('filters')->nullable();
            $table->json('layout')->nullable();
            $table->timestamps();
        });

        Schema::create('core_feature_flags', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->boolean('enabled')->default(false);
            $table->unsignedTinyInteger('rollout_percent')->default(100);
            $table->boolean('sandbox_only')->default(false);
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('core_sso_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('protocol', 10);
            $table->boolean('enabled')->default(false);
            $table->string('client_id')->nullable();
            $table->text('client_secret')->nullable();
            $table->string('issuer')->nullable();
            $table->string('metadata_url')->nullable();
            $table->string('redirect_uri')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('core_scim_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('token_hash');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_scim_tokens');
        Schema::dropIfExists('core_sso_providers');
        Schema::dropIfExists('core_feature_flags');
        Schema::dropIfExists('core_bi_reports');
        Schema::dropIfExists('core_workflow_runs');
        Schema::dropIfExists('core_workflows');
    }
};
