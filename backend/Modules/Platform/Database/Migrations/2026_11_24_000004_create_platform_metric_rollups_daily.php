<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('platform_metric_rollups_daily')) {
            Schema::create('platform_metric_rollups_daily', function (Blueprint $table) {
                $table->id();
                $table->foreignId('server_id')->nullable()->constrained('platform_servers')->cascadeOnDelete();
                $table->foreignId('project_id')->nullable()->constrained('platform_projects')->nullOnDelete();
                $table->foreignId('resource_id')->nullable()->constrained('platform_resources')->nullOnDelete();
                $table->string('scope', 32)->default('server');
                $table->string('metric_key', 64);
                $table->date('bucket_date');
                $table->decimal('value_avg', 20, 4);
                $table->decimal('value_max', 20, 4);
                $table->decimal('value_min', 20, 4);
                $table->unsignedInteger('sample_count')->default(1);
                $table->string('unit', 32)->default('count');
                $table->json('labels')->nullable();
                $table->timestamps();

                $table->index(['server_id', 'metric_key', 'bucket_date'], 'idx_pmrd_server_metric_date');
                $table->index(['resource_id', 'metric_key', 'bucket_date'], 'idx_pmrd_resource_metric_date');
                $table->index(['project_id', 'metric_key', 'bucket_date'], 'idx_pmrd_project_metric_date');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_metric_rollups_daily');
    }
};
