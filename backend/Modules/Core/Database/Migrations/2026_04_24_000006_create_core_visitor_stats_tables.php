<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_visits', function (Blueprint $table) {
            $table->id();
            $table->string('session_id', 64)->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip', 45)->nullable()->index();
            $table->text('user_agent')->nullable();
            $table->string('country', 2)->nullable();
            $table->string('device', 20)->nullable()->index();
            $table->string('referrer', 500)->nullable();
            $table->string('landing_path', 500)->nullable()->index();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('core_visitor_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained('core_visits')->cascadeOnDelete();
            $table->string('path', 500)->index();
            $table->string('title', 191)->nullable();
            $table->unsignedInteger('ms_on_page')->default(0);
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('core_visitor_daily', function (Blueprint $table) {
            $table->date('date')->primary();
            $table->unsignedInteger('uniques')->default(0);
            $table->unsignedInteger('visits')->default(0);
            $table->unsignedInteger('pageviews')->default(0);
            $table->unsignedInteger('avg_session_ms')->default(0);
            $table->timestamp('updated_at')->useCurrent()->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_visitor_daily');
        Schema::dropIfExists('core_visitor_pages');
        Schema::dropIfExists('core_visits');
    }
};
