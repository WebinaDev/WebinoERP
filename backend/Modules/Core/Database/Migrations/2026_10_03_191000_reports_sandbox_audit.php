<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('core_bi_reports', function (Blueprint $table) {
            $table->json('joins')->nullable();
            $table->json('schedule')->nullable();
            $table->timestamp('last_sent_at')->nullable();
        });

        Schema::table('ops_audit_logs', function (Blueprint $table) {
            $table->boolean('sandbox')->default(false);
            $table->index(['sandbox', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('ops_audit_logs', function (Blueprint $table) {
            $table->dropIndex(['sandbox', 'created_at']);
            $table->dropColumn('sandbox');
        });
        Schema::table('core_bi_reports', function (Blueprint $table) {
            $table->dropColumn(['joins', 'schedule', 'last_sent_at']);
        });
    }
};
