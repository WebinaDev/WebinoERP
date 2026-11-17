<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bi_subscriptions')) {
            Schema::create('bi_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->string('name', 160);
                $table->string('report_type', 48); // sales_margin, inventory_turnover, ar_aging, lead_conversion, project_profitability, support_sla, executive_summary
                $table->string('frequency', 20)->default('weekly'); // daily, weekly, monthly
                $table->string('format', 12)->default('csv'); // csv, json
                $table->json('recipients'); // list of emails or user IDs
                $table->json('filters')->nullable(); // company_id, branch_id, date_range
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_sent_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('bi_threshold_alerts')) {
            Schema::create('bi_threshold_alerts', function (Blueprint $table) {
                $table->id();
                $table->string('title', 160);
                $table->string('metric_key', 48); // low_stock, expiring_contracts, sla_breaches, high_ar_aging
                $table->string('comparator', 8)->default('gt'); // gt, lt, gte, lte, eq
                $table->decimal('threshold_value', 18, 2);
                $table->string('severity', 20)->default('warning'); // info, warning, critical
                $table->json('notify_channels')->nullable(); // in_app, email, sms
                $table->foreignId('notify_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_triggered_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bi_threshold_alerts');
        Schema::dropIfExists('bi_subscriptions');
    }
};
