<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('platform_service_health')) {
            Schema::create('platform_service_health', function (Blueprint $table) {
                $table->id();
                $table->foreignId('server_id')->constrained('platform_servers')->cascadeOnDelete();
                $table->string('service_key', 32); // caddy|nginx|php|octane|redis|queue|scheduler|mail
                $table->string('status', 16)->default('unknown'); // up|down|degraded|unknown
                $table->text('message')->nullable();
                $table->json('meta')->nullable();
                $table->timestamp('checked_at');
                $table->timestamps();

                $table->index(['server_id', 'service_key', 'checked_at'], 'idx_psh_server_srv_time');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_service_health');
    }
};
