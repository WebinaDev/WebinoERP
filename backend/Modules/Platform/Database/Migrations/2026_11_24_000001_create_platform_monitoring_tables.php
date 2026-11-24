<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('platform_metric_snapshots')) {
            Schema::create('platform_metric_snapshots', function (Blueprint $table) {
                $table->id();
                $table->foreignId('server_id')->constrained('platform_servers')->cascadeOnDelete();
                $table->foreignId('project_id')->nullable()->constrained('platform_projects')->nullOnDelete();
                $table->foreignId('resource_id')->nullable()->constrained('platform_resources')->nullOnDelete();
                $table->string('scope', 32)->default('server'); // server|project|resource|service
                $table->string('metric_key', 64);
                $table->decimal('value', 20, 4);
                $table->string('unit', 32)->default('count');
                $table->json('labels')->nullable();
                $table->timestamp('collected_at');
                $table->timestamps();

                $table->index(['server_id', 'metric_key', 'collected_at'], 'idx_pms_server_metric_time');
                $table->index(['resource_id', 'metric_key', 'collected_at'], 'idx_pms_resource_metric_time');
            });
        }

        if (! Schema::hasTable('platform_metric_rollups_hourly')) {
            Schema::create('platform_metric_rollups_hourly', function (Blueprint $table) {
                $table->id();
                $table->foreignId('server_id')->constrained('platform_servers')->cascadeOnDelete();
                $table->foreignId('project_id')->nullable()->constrained('platform_projects')->nullOnDelete();
                $table->foreignId('resource_id')->nullable()->constrained('platform_resources')->nullOnDelete();
                $table->string('scope', 32)->default('server');
                $table->string('metric_key', 64);
                $table->timestamp('bucket_start');
                $table->decimal('value_avg', 20, 4);
                $table->decimal('value_max', 20, 4);
                $table->decimal('value_min', 20, 4);
                $table->unsignedInteger('sample_count')->default(1);
                $table->string('unit', 32)->default('count');
                $table->json('labels')->nullable();
                $table->timestamps();

                $table->index(['server_id', 'metric_key', 'bucket_start'], 'idx_pmr_server_metric_bucket');
            });
        }

        if (! Schema::hasTable('platform_alert_rules')) {
            Schema::create('platform_alert_rules', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('scope_type', 32)->default('server'); // server|project|resource|global
                $table->unsignedBigInteger('scope_id')->nullable();
                $table->string('metric_key', 64);
                $table->string('operator', 8); // gt|gte|lt|lte
                $table->decimal('threshold', 20, 4);
                $table->unsignedInteger('duration_seconds')->default(300);
                $table->string('severity', 16)->default('warning'); // info|warning|critical
                $table->json('channel_ids')->nullable();
                $table->boolean('is_enabled')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('platform_alert_events')) {
            Schema::create('platform_alert_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('rule_id')->constrained('platform_alert_rules')->cascadeOnDelete();
                $table->foreignId('server_id')->nullable()->constrained('platform_servers')->nullOnDelete();
                $table->foreignId('project_id')->nullable()->constrained('platform_projects')->nullOnDelete();
                $table->foreignId('resource_id')->nullable()->constrained('platform_resources')->nullOnDelete();
                $table->string('status', 24)->default('open'); // open|acknowledged|resolved
                $table->string('severity', 16)->default('warning');
                $table->text('message');
                $table->decimal('metric_value', 20, 4);
                $table->timestamp('opened_at');
                $table->timestamp('acknowledged_at')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['status', 'server_id']);
                $table->index(['rule_id', 'server_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_alert_events');
        Schema::dropIfExists('platform_alert_rules');
        Schema::dropIfExists('platform_metric_rollups_hourly');
        Schema::dropIfExists('platform_metric_snapshots');
    }
};
