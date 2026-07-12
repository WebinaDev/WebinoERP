<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_automation_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->string('trigger', 80)->index();
            $table->json('conditions')->nullable();
            $table->json('actions');
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('priority')->default(100)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('core_automation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_id')->constrained('core_automation_rules')->cascadeOnDelete();
            $table->string('status', 20)->default('queued')->index();
            $table->json('event_payload')->nullable();
            $table->json('result_payload')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_automation_runs');
        Schema::dropIfExists('core_automation_rules');
    }
};
