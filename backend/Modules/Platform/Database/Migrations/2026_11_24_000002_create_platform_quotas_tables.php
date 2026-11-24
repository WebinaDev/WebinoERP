<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('platform_resource_quotas')) {
            Schema::create('platform_resource_quotas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('resource_id')->unique()->constrained('platform_resources')->cascadeOnDelete();
                $table->decimal('cpu_percent_soft', 8, 2)->nullable();
                $table->decimal('cpu_percent_hard', 8, 2)->nullable();
                $table->decimal('ram_mb_soft', 12, 2)->nullable();
                $table->decimal('ram_mb_hard', 12, 2)->nullable();
                $table->decimal('disk_mb_soft', 14, 2)->nullable();
                $table->decimal('disk_mb_hard', 14, 2)->nullable();
                $table->unsignedBigInteger('inode_soft')->nullable();
                $table->unsignedBigInteger('inode_hard')->nullable();
                $table->decimal('bandwidth_gb_month_soft', 12, 2)->nullable();
                $table->decimal('bandwidth_gb_month_hard', 12, 2)->nullable();
                $table->string('enforce_action', 32)->default('alert'); // alert|throttle|suspend
                $table->boolean('is_enabled')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('platform_project_quotas')) {
            Schema::create('platform_project_quotas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->unique()->constrained('platform_projects')->cascadeOnDelete();
                $table->decimal('cpu_percent_soft', 8, 2)->nullable();
                $table->decimal('cpu_percent_hard', 8, 2)->nullable();
                $table->decimal('ram_mb_soft', 12, 2)->nullable();
                $table->decimal('ram_mb_hard', 12, 2)->nullable();
                $table->decimal('disk_mb_soft', 14, 2)->nullable();
                $table->decimal('disk_mb_hard', 14, 2)->nullable();
                $table->unsignedBigInteger('inode_soft')->nullable();
                $table->unsignedBigInteger('inode_hard')->nullable();
                $table->decimal('bandwidth_gb_month_soft', 12, 2)->nullable();
                $table->decimal('bandwidth_gb_month_hard', 12, 2)->nullable();
                $table->string('enforce_action', 32)->default('alert'); // alert|throttle|suspend
                $table->boolean('is_enabled')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_project_quotas');
        Schema::dropIfExists('platform_resource_quotas');
    }
};
